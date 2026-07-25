<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserBulkImportService
{
    public const MAX_ROWS = SafeSpreadsheet::MAX_ACCOUNT_ROWS;

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

        if ($extension !== 'xlsx') {
            throw ValidationException::withMessages([
                'accounts_file' => 'Only the current official .xlsx account template is accepted.',
            ]);
        }

        $path = $file->storeAs('imports/user-accounts/uploads', Str::uuid().'.xlsx', 'local');

        if (! is_string($path)) {
            throw ValidationException::withMessages(['accounts_file' => 'The Excel file could not be stored securely.']);
        }

        $this->auditLog->record($actor, 'user.bulk_upload_initiated', metadata: [
            'account_type' => $type['key'],
            'format' => 'xlsx',
            'result' => 'started',
        ]);

        try {
            $rows = $this->readRows(Storage::disk('local')->path($path), $type);
            $result = $this->preflight($actor, $rows, $type);
            $result['account_type'] = $type;
            $result['preview_token'] = $result['valid_count'] > 0
                ? $this->storePreview($actor, $type, $result['valid_rows'])
                : null;

            $this->auditLog->record($actor, 'user.bulk_validation_completed', metadata: [
                'account_type' => $type['key'],
                'total_rows' => $result['total_count'],
                'valid_rows' => $result['valid_count'],
                'invalid_rows' => $result['invalid_count'],
                'duplicate_rows' => $result['duplicate_count'],
                'existing_rows' => $result['existing_count'],
                'result' => $result['invalid_count'] === 0 ? 'valid' : 'valid_with_exclusions',
            ]);

            return $result;
        } catch (Throwable $exception) {
            $this->auditLog->record($actor, 'user.bulk_validation_failed', metadata: [
                'account_type' => $type['key'],
                'reason' => class_basename($exception),
                'result' => 'failed',
            ]);

            throw $exception;
        } finally {
            Storage::disk('local')->delete($path);
            $this->cleanupExpiredFiles();
        }
    }

    /**
     * Confirm only the server-generated valid-row payload. Each account uses its own short transaction so an
     * unexpected row failure can be reported without holding mail delivery or unrelated rows in one transaction.
     *
     * @return array{created: int, existing: int, failed: int, emails_sent: int, emails_failed: int}
     */
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
                throw ValidationException::withMessages(['import_token' => 'This import preview has expired. Validate the Excel file again.']);
            }

            $type = $this->accountTypes->authorized($actor, (string) ($payload['account_type'] ?? ''));
            $rows = collect($payload['rows'] ?? [])->values();
            $emails = $rows->pluck('attributes.email')->filter()->unique()->values();
            $identifiers = $rows->pluck('attributes.institutional_identifier')->filter()->unique()->values();
            $usernames = $rows->pluck('generated_username')->filter()->unique()->values();
            $currentUsers = User::withTrashed()
                ->select(['id', 'email', 'institutional_identifier', 'username'])
                ->where(function ($query) use ($emails, $identifiers, $usernames): void {
                    $query
                        ->whereIn('email', $emails)
                        ->orWhereIn('institutional_identifier', $identifiers)
                        ->orWhereIn('username', $usernames);
                })
                ->get();
            $takenEmails = $currentUsers->pluck('email')->map(fn (string $email): string => Str::lower($email))->flip();
            $takenIdentifiers = $currentUsers->pluck('institutional_identifier')->filter()->map(fn (string $identifier): string => Str::upper($identifier))->flip();
            $takenUsernames = $currentUsers->pluck('username')->filter()->flip();
            $createdUsers = [];
            $existing = 0;
            $failed = 0;

            foreach ($rows as $row) {
                $attributes = $row['attributes'];
                $email = Str::lower((string) $attributes['email']);
                $identifier = Str::upper((string) $attributes['institutional_identifier']);
                $username = (string) $row['generated_username'];

                if ($takenEmails->has($email) || $takenIdentifiers->has($identifier)) {
                    $existing++;

                    continue;
                }

                if ($takenUsernames->has($username)) {
                    $failed++;
                    $this->recordRowFailure($actor, $type['key'], (int) $row['row'], 'username_conflict');

                    continue;
                }

                try {
                    $user = $this->accounts->createFromImport($actor, $attributes, $username);
                    $createdUsers[] = $user;
                    $takenEmails[$email] = true;
                    $takenIdentifiers[$identifier] = true;
                    $takenUsernames[$username] = true;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->recordRowFailure($actor, $type['key'], (int) $row['row'], class_basename($exception));
                }
            }

            $this->auditLog->record($actor, 'user.bulk_import_confirmed', metadata: [
                'account_type' => $type['key'],
                'created_count' => count($createdUsers),
                'existing_count' => $existing,
                'failed_count' => $failed,
                'result' => $failed === 0 ? 'created' : 'created_with_failures',
            ]);

            // External mail delivery starts only after all account transactions have completed.
            $delivery = $this->passwordResets->sendMany($actor, $createdUsers);
            $this->auditLog->record($actor, 'user.bulk_import_completed', metadata: [
                'account_type' => $type['key'],
                'created_count' => count($createdUsers),
                'existing_count' => $existing,
                'failed_count' => $failed,
                'emails_sent' => $delivery['sent'],
                'emails_failed' => $delivery['failed'],
                'result' => $failed === 0 && $delivery['failed'] === 0
                    ? 'completed'
                    : 'completed_with_failures',
            ]);

            return [
                'created' => count($createdUsers),
                'existing' => $existing,
                'failed' => $failed,
                'emails_sent' => $delivery['sent'],
                'emails_failed' => $delivery['failed'],
            ];
        } finally {
            @unlink($processing);
        }
    }

    /** @param array<string, mixed> $type @return array<int, array<string, string>> */
    private function readRows(string $path, array $type): array
    {
        $workbook = $this->spreadsheets->read($path, $type);
        $fields = array_values($type['template_columns']);
        $rows = [];

        foreach ($workbook['rows'] as $workbookRow) {
            /** @var array<string, string> $row */
            $row = array_combine($fields, $workbookRow['values']);

            // Only the exact sentinel may bypass validation; an ordinary Row 2 remains account data.
            if ($this->accountTypes->isExampleRow($type, $row)) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'accounts_file' => 'A single Excel import may contain at most '.self::MAX_ROWS.' account rows.',
                ]);
            }

            $row['_line'] = (string) $workbookRow['row'];
            $rows[] = $row;
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'accounts_file' => 'Add at least one account row to the Accounts worksheet.',
            ]);
        }

        return $rows;
    }

    /** @param array<int, array<string, string>> $rows @param array<string, mixed> $type @return array<string, mixed> */
    private function preflight(User $actor, array $rows, array $type): array
    {
        $validCandidates = [];
        $invalidRows = [];
        $duplicateRows = [];
        $existingRows = [];
        $seenEmails = [];
        $seenIdentifiers = [];
        $candidateEmails = collect($rows)
            ->pluck('email')
            ->map(fn ($email): string => Str::lower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
        $candidateIdentifiers = collect($rows)
            ->map(fn (array $row): string => Str::upper(trim((string) ($row[$type['identifier_field']] ?? ''))))
            ->filter()
            ->unique()
            ->values();
        $existingUsers = User::withTrashed()
            ->select(['id', 'email', 'institutional_identifier'])
            ->where(function ($query) use ($candidateEmails, $candidateIdentifiers): void {
                $query
                    ->whereIn('email', $candidateEmails)
                    ->orWhereIn('institutional_identifier', $candidateIdentifiers);
            })
            ->get();
        $existingByEmail = $existingUsers->keyBy(fn (User $user): string => Str::lower($user->email));
        $existingByIdentifier = $existingUsers
            ->filter(fn (User $user): bool => filled($user->institutional_identifier))
            ->keyBy(fn (User $user): string => Str::upper((string) $user->institutional_identifier));

        foreach ($rows as $row) {
            $line = (int) $row['_line'];
            unset($row['_line']);
            $attributes = $this->attributesFromRow($row, $type);
            $errors = $this->unsafeCellErrors($row, $type);

            if ($errors === []) {
                try {
                    $attributes = $this->accounts->validateCreation($actor, $attributes, false);
                } catch (ValidationException $exception) {
                    $errors = $this->validationErrors($exception, $row, $type);
                } catch (AuthorizationException $exception) {
                    $errors = [[
                        'field' => 'Account Type',
                        'value' => $type['label'],
                        'reason' => $exception->getMessage(),
                        'expected' => 'An account type authorized for the signed-in user.',
                    ]];
                }
            }

            if ($errors !== []) {
                $invalidRows[] = ['row' => $line, 'errors' => $errors];

                continue;
            }

            $email = Str::lower((string) $attributes['email']);
            $identifier = Str::upper((string) $attributes['institutional_identifier']);
            $emailOwner = $existingByEmail->get($email);
            $identifierOwner = $existingByIdentifier->get($identifier);

            if ($emailOwner || $identifierOwner) {
                $sameExistingAccount = $emailOwner
                    && $identifierOwner
                    && $emailOwner->id === $identifierOwner->id
                    && Str::lower($emailOwner->email) === $email
                    && Str::upper((string) $emailOwner->institutional_identifier) === $identifier;

                if ($sameExistingAccount) {
                    $existingRows[] = [
                        'row' => $line,
                        'reason' => 'This institutional identifier and email already belong to an existing account. The account will not be overwritten.',
                    ];
                } else {
                    $invalidRows[] = [
                        'row' => $line,
                        'errors' => [[
                            'field' => $emailOwner ? 'Email' : $type['identifier_header'],
                            'value' => $emailOwner ? $this->safeSubmittedValue($email) : $this->safeSubmittedValue($identifier),
                            'reason' => 'The email or institutional identifier belongs to a conflicting account.',
                            'expected' => 'A unique email and institutional identifier that refer to the same new person.',
                        ]],
                    ];
                }

                continue;
            }

            $seenByEmail = $seenEmails[$email] ?? null;
            $seenByIdentifier = $seenIdentifiers[$identifier] ?? null;

            if ($seenByEmail !== null || $seenByIdentifier !== null) {
                $sameWorkbookAccount = $seenByEmail === $identifier && $seenByIdentifier === $email;

                if ($sameWorkbookAccount) {
                    $duplicateRows[] = [
                        'row' => $line,
                        'reason' => 'Duplicate account in this workbook; only the first valid occurrence is eligible for creation.',
                    ];
                } else {
                    $invalidRows[] = [
                        'row' => $line,
                        'errors' => [[
                            'field' => $seenByEmail !== null ? 'Email' : $type['identifier_header'],
                            'value' => $this->safeSubmittedValue($seenByEmail !== null ? $email : $identifier),
                            'reason' => 'This value conflicts with another row in the workbook.',
                            'expected' => 'Each email and institutional identifier must identify only one account.',
                        ]],
                    ];
                }

                continue;
            }

            $seenEmails[$email] = $identifier;
            $seenIdentifiers[$identifier] = $email;
            $validCandidates[] = [
                'row' => $line,
                'name' => User::formatName(
                    $attributes['first_name'],
                    $attributes['middle_name'] ?? null,
                    $attributes['last_name'],
                    $attributes['suffix'] ?? null,
                ),
                'email' => $attributes['email'],
                'institutional_identifier' => $attributes['institutional_identifier'],
                'attributes' => $attributes,
            ];
        }

        $generatedUsernames = $this->usernames->generateBatch(collect($validCandidates)->map(fn (array $row): array => [
            'institutional_identifier' => $row['attributes']['institutional_identifier'],
            'last_name' => $row['attributes']['last_name'],
        ])->all());
        $validRows = collect($validCandidates)->values()->map(function (array $row, int $index) use ($generatedUsernames): array {
            $row['generated_username'] = $generatedUsernames[$index];

            return $row;
        })->all();
        $warnings = [];

        if ($invalidRows !== []) {
            $warnings[] = 'Invalid rows are excluded. Confirmation creates only rows classified as valid.';
        }

        if ($duplicateRows !== [] || $existingRows !== []) {
            $warnings[] = 'Duplicate and existing accounts are skipped and never overwritten.';
        }

        return [
            'total_count' => count($rows),
            'valid_count' => count($validRows),
            'invalid_count' => count($invalidRows),
            'duplicate_count' => count($duplicateRows),
            'existing_count' => count($existingRows),
            'estimated_create_count' => count($validRows),
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'duplicate_rows' => $duplicateRows,
            'existing_rows' => $existingRows,
            'warnings' => $warnings,
        ];
    }

    /** @param array<string, string> $row @param array<string, mixed> $type @return array<string, mixed> */
    private function attributesFromRow(array $row, array $type): array
    {
        $identifier = $row[$type['identifier_field']] ?? '';
        unset($row['student_number'], $row['employee_id']);

        return [
            ...$row,
            'institutional_identifier' => $identifier,
            'role' => $type['role'],
            'applicant_type' => $type['applicant_type'],
            'reviewer_capacity' => $type['key'] === 'reviewer' ? 30 : null,
        ];
    }

    /** @param array<string, string> $row @param array<string, mixed> $type @return array<int, array<string, string>> */
    private function unsafeCellErrors(array $row, array $type): array
    {
        $errors = [];

        foreach ($row as $field => $value) {
            $label = $this->fieldLabel($type, $field);

            if ($value !== '' && preg_match('/^[=+\-@]/', $value) === 1) {
                $errors[] = [
                    'field' => $label,
                    'value' => '[unsafe value omitted]',
                    'reason' => 'A formula-like or executable spreadsheet value was found.',
                    'expected' => 'Plain text that does not begin with =, +, -, or @.',
                ];
            }

            if ($value !== strip_tags($value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
                $errors[] = [
                    'field' => $label,
                    'value' => '[unsafe value omitted]',
                    'reason' => 'HTML or unsupported control characters were found.',
                    'expected' => 'Plain readable text.',
                ];
            }
        }

        return $errors;
    }

    /** @param array<string, string> $row @param array<string, mixed> $type @return array<int, array<string, string>> */
    private function validationErrors(ValidationException $exception, array $row, array $type): array
    {
        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            $sourceField = $field === 'institutional_identifier' ? $type['identifier_field'] : $field;

            foreach ($messages as $message) {
                $errors[] = [
                    'field' => $this->fieldLabel($type, $sourceField),
                    'value' => $this->safeSubmittedValue((string) ($row[$sourceField] ?? '')),
                    'reason' => $message,
                    'expected' => $this->expectedFormat($field),
                ];
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $type */
    private function fieldLabel(array $type, string $field): string
    {
        $label = array_search($field, $type['template_columns'], true);

        return is_string($label) ? $label : Str::headline($field);
    }

    private function expectedFormat(string $field): string
    {
        return match ($field) {
            'email' => 'A valid unique email address, such as name@example.com.',
            'institutional_identifier' => 'The official unique Student Number or Employee ID using letters, numbers, periods, underscores, or hyphens.',
            'year_level', 'institution', 'department', 'program', 'reviewer_classification' => 'A current active value from the database-backed dropdown list.',
            'first_name', 'middle_name', 'last_name', 'suffix', 'position_title' => 'Plain text within the documented length limit.',
            'phone_number' => 'Digits with optional spaces, +, parentheses, periods, or hyphens.',
            default => 'A value accepted by the selected official account template.',
        };
    }

    private function safeSubmittedValue(string $value): string
    {
        if ($value === '' || preg_match('/^[=+\-@]/', $value) === 1 || $value !== strip_tags($value)) {
            return $value === '' ? '(blank)' : '[unsafe value omitted]';
        }

        return Str::limit($value, 120);
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

    /** @param array<string, mixed> $payload */
    private function writePayload(string $path, array $payload): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($path, 0600);
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

    private function cleanupExpiredFiles(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subMinutes(self::PREVIEW_TTL_MINUTES)->timestamp;

        foreach ($disk->allFiles('imports/user-accounts/previews') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }

    private function previewPath(User $actor, string $token): string
    {
        $this->assertUuid($token);

        return Storage::disk('local')->path('imports/user-accounts/previews/'.$actor->id.'/'.$token.'.json');
    }

    private function assertUuid(string $token): void
    {
        if (! Str::isUuid($token)) {
            throw ValidationException::withMessages(['import_token' => 'The import token is invalid.']);
        }
    }

    private function recordRowFailure(User $actor, string $accountType, int $row, string $reason): void
    {
        $this->auditLog->record($actor, 'user.bulk_import_row_failed', metadata: [
            'account_type' => $accountType,
            'row' => $row,
            'reason' => $reason,
            'result' => 'failed',
        ]);
    }
}
