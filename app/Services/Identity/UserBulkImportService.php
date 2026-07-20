<?php

namespace App\Services\Identity;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
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
    private const MAX_ROWS = 250;

    private const REQUIRED_HEADERS = [
        'account_type',
        'first_name',
        'last_name',
        'email',
        'institutional_identifier',
        'password',
    ];

    private const OPTIONAL_HEADERS = [
        'middle_name',
        'suffix',
        'phone_number',
        'institution',
        'department',
        'position_title',
    ];

    public function __construct(
        private readonly UserAccountService $accounts,
        private readonly AuditLogService $auditLog,
    ) {}

    /** @return array{created: int} */
    public function import(User $actor, UploadedFile $file): array
    {
        $path = $file->storeAs('imports/user-accounts', Str::uuid().'.csv', 'local');

        if (! is_string($path)) {
            throw ValidationException::withMessages(['accounts_file' => 'The CSV file could not be stored securely.']);
        }

        try {
            $rows = $this->readRows(Storage::disk('local')->path($path));
            $validatedRows = $this->preflight($actor, $rows);

            // A bounded all-or-nothing transaction prevents partially imported account batches.
            DB::transaction(function () use ($actor, $validatedRows): void {
                foreach ($validatedRows as $row) {
                    $row['password_confirmation'] = $row['password'];
                    $this->accounts->create($actor, $row);
                }

                $this->auditLog->record($actor, 'user.bulk_imported', metadata: [
                    'created_count' => count($validatedRows),
                ]);
            });

            return ['created' => count($validatedRows)];
        } finally {
            // Plain-text onboarding passwords never remain in application storage after processing.
            Storage::disk('local')->delete($path);
        }
    }

    /** @return array<int, array<string, string>> */
    private function readRows(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['accounts_file' => 'The CSV file could not be read.']);
        }

        try {
            $sample = fread($handle, 4096);

            if ($sample === false || str_contains($sample, "\0") || ! mb_check_encoding($sample, 'UTF-8')) {
                throw ValidationException::withMessages(['accounts_file' => 'The uploaded file is not valid text CSV content.']);
            }

            rewind($handle);
            $header = fgetcsv($handle, 0, ',', '"', '\\');

            if (! is_array($header)) {
                throw ValidationException::withMessages(['accounts_file' => 'The CSV file is empty or missing its header row.']);
            }

            $headers = array_map(
                fn (string $value): string => Str::of($value)->replace("\xEF\xBB\xBF", '')->trim()->lower()->value(),
                $header,
            );
            $this->validateHeaders($headers);
            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $line++;

                if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'accounts_file' => 'A single import may contain at most '.self::MAX_ROWS.' account rows.',
                    ]);
                }

                if (count($values) !== count($headers)) {
                    throw ValidationException::withMessages([
                        'accounts_file' => "CSV row {$line} does not match the template column count.",
                    ]);
                }

                /** @var array<string, string> $row */
                $row = array_combine($headers, array_map(fn ($value): string => trim((string) $value), $values));
                $row['_line'] = (string) $line;
                $rows[] = $row;
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['accounts_file' => 'Add at least one account row below the CSV header.']);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @param array<int, string> $headers */
    private function validateHeaders(array $headers): void
    {
        $allowed = [...self::REQUIRED_HEADERS, ...self::OPTIONAL_HEADERS];
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        $unknown = array_values(array_diff($headers, $allowed));

        if (count($headers) !== count(array_unique($headers)) || $missing !== [] || $unknown !== []) {
            $parts = [];

            if ($missing !== []) {
                $parts[] = 'missing: '.implode(', ', $missing);
            }

            if ($unknown !== []) {
                $parts[] = 'unknown: '.implode(', ', $unknown);
            }

            if (count($headers) !== count(array_unique($headers))) {
                $parts[] = 'duplicate header names';
            }

            throw ValidationException::withMessages([
                'accounts_file' => 'The CSV header does not match the template ('.implode('; ', $parts).').',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function preflight(User $actor, array $rows): array
    {
        $validatedRows = [];
        $errors = [];
        $seenEmails = [];
        $seenIdentifiers = [];

        foreach ($rows as $row) {
            $line = (int) $row['_line'];
            unset($row['_line']);

            try {
                [$role, $applicantType] = $this->resolveAccountType($row['account_type'] ?? '');
                $attributes = [
                    ...$row,
                    'role' => $role,
                    'applicant_type' => $applicantType,
                    'password_confirmation' => $row['password'] ?? '',
                ];
                unset($attributes['account_type']);

                $emailKey = Str::lower(trim((string) ($attributes['email'] ?? '')));
                $identifierKey = Str::upper(trim((string) ($attributes['institutional_identifier'] ?? '')));

                if (isset($seenEmails[$emailKey]) || isset($seenIdentifiers[$identifierKey])) {
                    throw ValidationException::withMessages([
                        'row' => 'Email addresses and institutional identifiers must be unique within the file.',
                    ]);
                }

                $validatedRows[] = $this->accounts->validateCreation($actor, $attributes);
                $seenEmails[$emailKey] = true;
                $seenIdentifiers[$identifierKey] = true;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?? 'The row is invalid.';
                $errors[] = "Row {$line}: {$message}";
            } catch (AuthorizationException $exception) {
                $errors[] = "Row {$line}: {$exception->getMessage()}";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['accounts_file' => array_slice($errors, 0, 10)]);
        }

        return $validatedRows;
    }

    /** @return array{UserRole, ApplicantType|null} */
    private function resolveAccountType(string $value): array
    {
        return match (Str::of($value)->lower()->replace([' ', '-'], '_')->value()) {
            'student', 'student_researcher' => [UserRole::Applicant, ApplicantType::Student],
            'faculty', 'faculty_researcher' => [UserRole::Applicant, ApplicantType::Faculty],
            'adviser', 'research_adviser' => [UserRole::Adviser, null],
            'reviewer', 'ethics_reviewer' => [UserRole::Reviewer, null],
            default => throw ValidationException::withMessages([
                'account_type' => 'Use student_researcher, faculty_researcher, adviser, or reviewer.',
            ]),
        };
    }
}
