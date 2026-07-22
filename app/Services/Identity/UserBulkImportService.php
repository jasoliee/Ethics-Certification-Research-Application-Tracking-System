<?php

namespace App\Services\Identity;

use App\Enums\ApplicantType;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserBulkImportService
{
    public const MAX_ROWS = 250;

    public const MAX_FILE_KILOBYTES = 2048;

    private const PREVIEW_TTL_MINUTES = 30;

    public function __construct(
        private readonly UserAccountService $accounts,
        private readonly UsernameGenerator $usernames,
        private readonly AccountTypeCatalog $accountTypes,
        private readonly SafeSpreadsheet $spreadsheets,
        private readonly ManagedPasswordResetService $passwordResets,
        private readonly AuditLogService $auditLog,
    ) {}

    /** @return array<string, mixed> */
    public function preview(User $actor, UploadedFile $file, string $accountType): array
    {
        $type = $this->accountTypes->authorized($actor, $accountType);
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('imports/user-accounts/uploads', Str::uuid().'.'.$extension, 'local');

        if (! is_string($path)) {
            throw ValidationException::withMessages(['accounts_file' => 'The account file could not be stored securely.']);
        }

        $this->auditLog->record($actor, 'user.bulk_upload_initiated', metadata: [
            'account_type' => $type['key'],
            'format' => $extension,
            'result' => 'started',
        ]);

        try {
            $rows = $this->readRows(Storage::disk('local')->path($path), $extension, $type);
            $result = $this->preflight($actor, $rows, $type);
            $result['account_type'] = $type;
            $result['preview_token'] = null;
            $result['error_token'] = null;

            if ($result['invalid_count'] === 0) {
                $result['preview_token'] = $this->storePreview($actor, $type, $result['valid_rows']);
            } else {
                $result['error_token'] = $this->storeErrorReport($actor, $result['invalid_rows']);
            }

            $this->auditLog->record($actor, 'user.bulk_validation_completed', metadata: [
                'account_type' => $type['key'],
                'total_rows' => $result['total_count'],
                'valid_rows' => $result['valid_count'],
                'invalid_rows' => $result['invalid_count'],
                'duplicate_rows' => $result['duplicate_count'],
                'existing_conflicts' => $result['existing_conflict_count'],
                'result' => $result['invalid_count'] === 0 ? 'valid' : 'invalid',
            ]);

            return $result;
        } finally {
            Storage::disk('local')->delete($path);
            $this->cleanupExpiredFiles($actor);
        }
    }

    /** @return array{created: int, emails_sent: int, emails_failed: int} */
    public function confirm(User $actor, string $token): array
    {
        $source = $this->previewPath($actor, $token);
        $processing = $source.'.processing';

        // Atomic rename makes confirmation single-use across refreshes and double clicks.
        if (! is_file($source) || ! @rename($source, $processing)) {
            throw ValidationException::withMessages([
                'import_token' => 'This import preview is expired, already confirmed, or does not belong to you.',
            ]);
        }

        try {
            $payload = $this->readPayload($processing);

            if ((int) ($payload['actor_id'] ?? 0) !== $actor->id
                || now()->timestamp - (int) ($payload['created_at'] ?? 0) > self::PREVIEW_TTL_MINUTES * 60) {
                throw ValidationException::withMessages(['import_token' => 'This import preview has expired. Validate the file again.']);
            }

            $type = $this->accountTypes->authorized($actor, (string) ($payload['account_type'] ?? ''));
            $createdUsers = DB::transaction(function () use ($actor, $payload): array {
                $created = [];

                foreach ($payload['rows'] ?? [] as $row) {
                    $created[] = $this->accounts->create(
                        $actor,
                        $row['attributes'],
                        $row['generated_username'],
                    );
                }

                $this->auditLog->record($actor, 'user.bulk_import_confirmed', metadata: [
                    'account_type' => $payload['account_type'],
                    'created_count' => count($created),
                    'result' => 'created',
                ]);

                return $created;
            });

            $delivery = $this->passwordResets->sendMany($actor, $createdUsers);
            $this->auditLog->record($actor, 'user.bulk_import_completed', metadata: [
                'account_type' => $type['key'],
                'created_count' => count($createdUsers),
                'emails_sent' => $delivery['sent'],
                'emails_failed' => $delivery['failed'],
                'result' => $delivery['failed'] === 0 ? 'completed' : 'completed_with_email_failures',
            ]);

            return [
                'created' => count($createdUsers),
                'emails_sent' => $delivery['sent'],
                'emails_failed' => $delivery['failed'],
            ];
        } finally {
            @unlink($processing);
        }
    }

    /** @return array<int, array{row: int, errors: array<int, string>}> */
    public function errorReport(User $actor, string $token): array
    {
        $path = $this->errorPath($actor, $token);

        if (! is_file($path)) {
            throw ValidationException::withMessages(['error_token' => 'The error report is unavailable or expired.']);
        }

        $payload = $this->readPayload($path);

        if ((int) ($payload['actor_id'] ?? 0) !== $actor->id
            || now()->timestamp - (int) ($payload['created_at'] ?? 0) > self::PREVIEW_TTL_MINUTES * 60) {
            @unlink($path);
            throw ValidationException::withMessages(['error_token' => 'The error report has expired.']);
        }

        return $payload['errors'] ?? [];
    }

    /** @param array<string, mixed> $type @return array<int, array<string, string>> */
    private function readRows(string $path, string $extension, array $type): array
    {
        $matrix = match ($extension) {
            'csv' => $this->readCsv($path),
            'xlsx' => $this->spreadsheets->read($path),
            default => throw ValidationException::withMessages(['accounts_file' => 'Upload a CSV or XLSX file.']),
        };

        if ($matrix === []) {
            throw ValidationException::withMessages(['accounts_file' => 'The account file is empty.']);
        }

        $headers = array_map(
            fn ($value): string => Str::of((string) $value)->replace("\xEF\xBB\xBF", '')->trim()->lower()->value(),
            array_shift($matrix),
        );
        $this->validateHeaders($headers, $type);
        $rows = [];

        foreach ($matrix as $index => $values) {
            if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::withMessages(['accounts_file' => 'A single import may contain at most '.self::MAX_ROWS.' account rows.']);
            }

            $values = array_values($values);

            if (count($values) > count($headers)) {
                throw ValidationException::withMessages(['accounts_file' => 'Row '.($index + 2).' contains more cells than the template header.']);
            }

            $values = array_pad($values, count($headers), '');
            /** @var array<string, string> $row */
            $row = array_combine($headers, array_map(fn ($value): string => trim((string) $value), $values));
            $row['_line'] = (string) ($index + 2);
            $rows[] = $row;
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['accounts_file' => 'Add at least one account row below the header.']);
        }

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(string $path): array
    {
        $contents = file_get_contents($path);

        if (! is_string($contents)
            || str_contains($contents, "\0")
            || ! mb_check_encoding($contents, 'UTF-8')
            || str_starts_with($contents, "PK\x03\x04")) {
            throw ValidationException::withMessages(['accounts_file' => 'The uploaded file is not valid UTF-8 CSV content.']);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['accounts_file' => 'The CSV file could not be read.']);
        }

        try {
            $rows = [];

            while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rows[] = array_map(fn ($value): string => (string) $value, $values);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @param array<int, string> $headers @param array<string, mixed> $type */
    private function validateHeaders(array $headers, array $type): void
    {
        $required = $type['required_headers'];
        $allowed = [...$required, ...$type['optional_headers']];
        $missing = array_values(array_diff($required, $headers));
        $unknown = array_values(array_diff($headers, $allowed));
        $duplicates = count($headers) !== count(array_unique($headers));

        if ($missing === [] && $unknown === [] && ! $duplicates) {
            return;
        }

        $parts = [];
        if ($missing !== []) {
            $parts[] = 'missing: '.implode(', ', $missing);
        }
        if ($unknown !== []) {
            $parts[] = 'unknown: '.implode(', ', $unknown);
        }
        if ($duplicates) {
            $parts[] = 'duplicate header names';
        }

        throw ValidationException::withMessages(['accounts_file' => 'The file header does not match the selected role template ('.implode('; ', $parts).').']);
    }

    /** @param array<int, array<string, string>> $rows @param array<string, mixed> $type @return array<string, mixed> */
    private function preflight(User $actor, array $rows, array $type): array
    {
        $validRows = [];
        $invalidRows = [];
        $seenEmails = [];
        $seenIdentifiers = [];
        $reservedUsernames = [];
        $duplicateCount = 0;
        $existingConflictCount = 0;
        $candidateEmails = collect($rows)
            ->pluck('email')
            ->map(fn ($email): string => Str::lower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
        $candidateIdentifiers = collect($rows)
            ->map(fn (array $row): string => Str::upper(trim((string) ($row[$type['identifier_header']] ?? ''))))
            ->filter()
            ->unique()
            ->values();
        $existing = User::query()
            ->select(['email', 'institutional_identifier'])
            ->whereIn('email', $candidateEmails)
            ->orWhereIn('institutional_identifier', $candidateIdentifiers)
            ->get();
        $existingEmails = $existing->pluck('email')->map(fn (string $email): string => Str::lower($email))->flip();
        $existingIdentifiers = $existing->pluck('institutional_identifier')->map(fn (string $identifier): string => Str::upper($identifier))->flip();

        foreach ($rows as $row) {
            $line = (int) $row['_line'];
            unset($row['_line']);
            $errors = $this->unsafeCellErrors($row);

            if (($row['template_version'] ?? '') !== AccountTypeCatalog::TEMPLATE_VERSION) {
                $errors[] = 'Template version must be '.AccountTypeCatalog::TEMPLATE_VERSION.'.';
            }

            if ($type['applicant_type'] !== null) {
                $providedType = Str::of($row['applicant_type'] ?? '')->lower()->replace([' ', '-'], '_')->value();
                $allowedValues = $type['applicant_type'] === ApplicantType::Student->value
                    ? ['student', 'student_researcher']
                    : ['faculty', 'faculty_researcher'];

                if (! in_array($providedType, $allowedValues, true)) {
                    $errors[] = 'Applicant type does not match the selected account template.';
                }
            }

            $attributes = $this->attributesFromRow($row, $type);
            $emailKey = Str::lower(trim((string) ($attributes['email'] ?? '')));
            $identifierKey = Str::upper(trim((string) ($attributes['institutional_identifier'] ?? '')));

            if (($emailKey !== '' && isset($seenEmails[$emailKey])) || ($identifierKey !== '' && isset($seenIdentifiers[$identifierKey]))) {
                $errors[] = 'Email addresses and institutional identifiers must be unique within the file.';
                $duplicateCount++;
            }

            if (($emailKey !== '' && $existingEmails->has($emailKey))
                || ($identifierKey !== '' && $existingIdentifiers->has($identifierKey))) {
                $errors[] = 'The email address or institutional identifier already belongs to an account.';
                $existingConflictCount++;
            }

            if ($errors === []) {
                try {
                    $validated = $this->accounts->validateCreation($actor, $attributes, false);
                    $username = $this->usernames->generate(
                        $validated['institutional_identifier'],
                        $validated['last_name'],
                        $reservedUsernames,
                    );
                    $reservedUsernames[] = $username;
                    $validRows[] = [
                        'row' => $line,
                        'name' => User::formatName($validated['first_name'], $validated['middle_name'] ?? null, $validated['last_name'], $validated['suffix'] ?? null),
                        'email' => $validated['email'],
                        'institutional_identifier' => $validated['institutional_identifier'],
                        'generated_username' => $username,
                        'attributes' => $validated,
                    ];
                } catch (ValidationException $exception) {
                    $errors = collect($exception->errors())->flatten()->values()->all();
                } catch (AuthorizationException $exception) {
                    $errors = [$exception->getMessage()];
                }
            }

            if ($errors !== []) {
                $invalidRows[] = ['row' => $line, 'errors' => array_values(array_unique($errors))];
            }

            if ($emailKey !== '') {
                $seenEmails[$emailKey] = true;
            }
            if ($identifierKey !== '') {
                $seenIdentifiers[$identifierKey] = true;
            }
        }

        return [
            'total_count' => count($rows),
            'valid_count' => count($validRows),
            'invalid_count' => count($invalidRows),
            'duplicate_count' => $duplicateCount,
            'existing_conflict_count' => $existingConflictCount,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'warnings' => $invalidRows === [] ? [] : ['No accounts will be created until every row passes validation.'],
        ];
    }

    /** @param array<string, string> $row @param array<string, mixed> $type @return array<string, mixed> */
    private function attributesFromRow(array $row, array $type): array
    {
        $identifier = $row[$type['identifier_header']] ?? '';
        unset($row['template_version'], $row['applicant_type'], $row['student_number'], $row['employee_id']);

        return [
            ...$row,
            'institutional_identifier' => $identifier,
            'role' => $type['role'],
            'applicant_type' => $type['applicant_type'],
        ];
    }

    /** @param array<string, string> $row @return array<int, string> */
    private function unsafeCellErrors(array $row): array
    {
        $errors = [];

        foreach ($row as $header => $value) {
            if ($value !== '' && preg_match('/^[=+\-@]/', $value) === 1) {
                $errors[] = "{$header} contains a spreadsheet formula or unsafe value.";
            }

            if ($value !== strip_tags($value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
                $errors[] = "{$header} contains HTML or unsupported control characters.";
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $type @param array<int, array<string, mixed>> $rows */
    private function storePreview(User $actor, array $type, array $rows): string
    {
        $token = (string) Str::uuid();
        $path = $this->previewPath($actor, $token);
        $this->writePayload($path, [
            'actor_id' => $actor->id,
            'account_type' => $type['key'],
            'created_at' => now()->timestamp,
            'rows' => $rows,
        ]);

        return $token;
    }

    /** @param array<int, array{row: int, errors: array<int, string>}> $errors */
    private function storeErrorReport(User $actor, array $errors): string
    {
        $token = (string) Str::uuid();
        $path = $this->errorPath($actor, $token);
        $this->writePayload($path, [
            'actor_id' => $actor->id,
            'created_at' => now()->timestamp,
            'errors' => $errors,
        ]);

        return $token;
    }

    /** @param array<string, mixed> $payload */
    private function writePayload(string $path, array $payload): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    /** @return array<string, mixed> */
    private function readPayload(string $path): array
    {
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw ValidationException::withMessages(['import_token' => 'The import preview could not be read.']);
        }

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }

    private function cleanupExpiredFiles(User $actor): void
    {
        foreach ([$this->previewDirectory($actor), $this->errorDirectory($actor)] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (glob($directory.'/*.json') ?: [] as $path) {
                if (filemtime($path) !== false && filemtime($path) < now()->subMinutes(self::PREVIEW_TTL_MINUTES)->timestamp) {
                    @unlink($path);
                }
            }
        }
    }

    private function previewPath(User $actor, string $token): string
    {
        $this->assertUuid($token);

        return $this->previewDirectory($actor).DIRECTORY_SEPARATOR.$token.'.json';
    }

    private function errorPath(User $actor, string $token): string
    {
        $this->assertUuid($token);

        return $this->errorDirectory($actor).DIRECTORY_SEPARATOR.$token.'.json';
    }

    private function previewDirectory(User $actor): string
    {
        return Storage::disk('local')->path('imports/user-accounts/previews/'.$actor->id);
    }

    private function errorDirectory(User $actor): string
    {
        return Storage::disk('local')->path('imports/user-accounts/error-reports/'.$actor->id);
    }

    private function assertUuid(string $token): void
    {
        if (! Str::isUuid($token)) {
            throw ValidationException::withMessages(['import_token' => 'The import token is invalid.']);
        }
    }
}
