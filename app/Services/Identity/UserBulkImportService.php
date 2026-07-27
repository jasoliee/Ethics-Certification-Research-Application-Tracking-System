<?php

namespace App\Services\Identity;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

/**
 * Validates account workbooks, persists private previews, creates valid rows, and restores archived matches.
 */
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
            // Keep a private preview when either account creation or authorized archive restoration is possible.
            $result['preview_token'] = $result['valid_count'] > 0 || $result['archived_count'] > 0
                ? $this->storePreview($actor, $type, $result)
                : null;

            $this->auditLog->record($actor, 'user.bulk_validation_completed', metadata: [
                'account_type' => $type['key'],
                'total_rows' => $result['total_count'],
                'valid_rows' => $result['valid_count'],
                'invalid_rows' => $result['invalid_count'],
                'duplicate_rows' => $result['duplicate_count'],
                'existing_rows' => $result['existing_count'],
                'active_existing_accounts' => $result['active_existing_count'],
                'archived_accounts' => $result['archived_count'],
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
            // Confirmation consumes only server-stored valid rows and never browser-submitted account data.
            $rows = collect($payload['preview']['valid_rows'] ?? [])->values();
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

    /**
     * Reload an actor-owned preview after a restoration redirect without exposing its token in the URL.
     *
     * @return array<string, mixed>
     */
    public function previewFor(User $actor, string $token): array
    {
        $payload = $this->readPayload($this->previewPath($actor, $token));
        $this->assertUsablePreview($actor, $payload);

        return $this->presentPreview($actor, $token, $payload);
    }

    /**
     * Restore either one preview-listed archived account or every archived account in that preview.
     *
     * @return array{preview: array<string, mixed>, restored_now: int, conflicts_now: int}
     */
    public function restore(User $actor, string $token, ?int $archivedUserId = null): array
    {
        // Defense in depth keeps restoration RES Lead-only even if a route is moved accidentally.
        if ($actor->role !== UserRole::ResLead) {
            throw new AuthorizationException('Only the RES Lead may restore archived accounts.');
        }

        $path = $this->previewPath($actor, $token);
        $handle = is_file($path) ? fopen($path, 'r+') : false;

        if ($handle === false || ! flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw ValidationException::withMessages([
                'import_token' => 'This import preview is expired, replaced, confirmed, or does not belong to you.',
            ]);
        }

        try {
            // Hold an exclusive preview-file lock while selecting authoritative server-stored targets.
            rewind($handle);
            $contents = stream_get_contents($handle);
            $payload = is_string($contents)
                ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR)
                : [];
            $this->assertUsablePreview($actor, $payload);
            $type = $this->accountTypes->authorized($actor, (string) $payload['account_type']);

            $preview = $payload['preview'] ?? [];
            $archivedAccounts = collect($preview['archived_accounts'] ?? [])->values();
            $targets = $archivedUserId === null
                ? $archivedAccounts
                : $archivedAccounts->where('id', $archivedUserId)->values();

            // An individual browser-provided ID is valid only when the private preview listed it.
            if ($archivedUserId !== null && $targets->isEmpty()) {
                throw ValidationException::withMessages([
                    'archived_user_id' => 'The selected archived account is not part of this import preview.',
                ]);
            }

            $restoredNow = [];
            $conflictsNow = [];

            // Each target restores in its own transaction so one conflict cannot roll back unrelated successes.
            foreach ($targets as $reference) {
                try {
                    $outcome = $this->restoreReference($actor, $reference, $type);

                    if ($outcome['restored']) {
                        $restoredNow[] = $outcome['account'];
                    } else {
                        $conflictsNow[] = [
                            ...$reference,
                            'reason' => $outcome['reason'],
                        ];
                    }
                } catch (Throwable $exception) {
                    $conflictsNow[] = [
                        ...$reference,
                        'reason' => 'The account could not be restored safely. It remains archived.',
                    ];

                    // Unexpected per-row failures remain bounded and auditable without exposing raw exception text.
                    $this->auditLog->record($actor, 'user.archived_account_restore_blocked', metadata: [
                        'account_type' => $type['key'],
                        'archived_user_id' => (int) ($reference['id'] ?? 0),
                        'excel_row' => (int) ($reference['row'] ?? 0),
                        'reason' => class_basename($exception),
                        'result' => 'failed',
                    ]);
                }
            }

            $restoredIds = collect($restoredNow)->pluck('id')->map(fn ($id): int => (int) $id);
            $targetIds = $targets->pluck('id')->map(fn ($id): int => (int) $id);
            $remainingArchived = $archivedAccounts
                ->reject(fn (array $reference): bool => $restoredIds->contains((int) $reference['id']))
                ->values()
                ->all();
            $restoredAccounts = collect($preview['restored_accounts'] ?? [])
                ->concat($restoredNow)
                ->unique('id')
                ->values()
                ->all();
            $restorationConflicts = collect($preview['restoration_conflicts'] ?? [])
                ->reject(fn (array $reference): bool => $targetIds->contains((int) ($reference['id'] ?? 0)))
                ->concat($conflictsNow)
                ->filter(fn (array $reference): bool => collect($remainingArchived)
                    ->contains(fn (array $archived): bool => (int) $archived['id'] === (int) ($reference['id'] ?? 0)))
                ->unique('id')
                ->values()
                ->all();

            // Refresh every server-authoritative category before persisting the still-single-use preview.
            $preview['archived_accounts'] = $remainingArchived;
            $preview['restored_accounts'] = $restoredAccounts;
            $preview['restoration_conflicts'] = $restorationConflicts;
            $preview['archived_count'] = count($remainingArchived);
            $preview['restored_count'] = count($restoredAccounts);
            $preview['restoration_conflict_count'] = count($restorationConflicts);
            $preview['existing_count'] = (int) ($preview['active_existing_count'] ?? 0)
                + $preview['archived_count']
                + $preview['restored_count'];
            $payload['preview'] = $preview;

            // Rewrite the locked preview atomically without changing its owner or expiry timestamp.
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($payload, JSON_THROW_ON_ERROR));
            fflush($handle);

            // Bulk restoration receives one summary audit in addition to each restored-account event.
            if ($archivedUserId === null) {
                $this->auditLog->record($actor, 'user.bulk_archived_accounts_restored', metadata: [
                    'account_type' => $type['key'],
                    'restored_count' => count($restoredNow),
                    'conflict_count' => count($conflictsNow),
                    'result' => $conflictsNow === [] ? 'restored' : 'restored_with_conflicts',
                ]);
            }

            return [
                'preview' => $this->presentPreview($actor, $token, $payload),
                'restored_now' => count($restoredNow),
                'conflicts_now' => count($conflictsNow),
            ];
        } finally {
            // Release the preview mutation lock even when validation or one restoration fails.
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Restore one original user row after verifying preview identity and active-account conflicts.
     *
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $type
     * @return array{restored: bool, account?: array<string, mixed>, reason?: string}
     */
    private function restoreReference(User $actor, array $reference, array $type): array
    {
        return DB::transaction(function () use ($actor, $reference, $type): array {
            // Lock the original row through withTrashed before rechecking archive state and preview identity.
            $user = User::withTrashed()
                ->whereKey((int) ($reference['id'] ?? 0))
                ->lockForUpdate()
                ->first();

            if (! $user || ! $this->matchesPreviewReference($user, $reference, $type)) {
                // Record the blocked target without storing workbook contents or untrusted exception text.
                $this->auditLog->record($actor, 'user.archived_account_restore_blocked', $user, [
                    'account_type' => $type['key'],
                    'archived_user_id' => (int) ($reference['id'] ?? 0),
                    'excel_row' => (int) ($reference['row'] ?? 0),
                    'reason' => 'preview_identity_mismatch',
                    'result' => 'blocked',
                ]);

                return [
                    'restored' => false,
                    'reason' => 'The archived account no longer matches the validated import preview.',
                ];
            }

            // A row restored outside this preview is no longer eligible for the archived restoration action.
            if (! $user->trashed()) {
                $this->auditLog->record($actor, 'user.archived_account_restore_blocked', $user, [
                    'account_type' => $type['key'],
                    'archived_user_id' => $user->id,
                    'excel_row' => (int) ($reference['row'] ?? 0),
                    'reason' => 'account_no_longer_archived',
                    'result' => 'blocked',
                ]);

                return [
                    'restored' => false,
                    'reason' => 'The account is no longer archived and was not changed.',
                ];
            }

            $email = Str::lower($user->email);
            $identifier = Str::upper((string) $user->institutional_identifier);
            $username = Str::lower((string) $user->username);

            // Active rows using any normalized identity prevent an unsafe restore and remain untouched.
            $hasConflict = User::query()
                ->whereKeyNot($user->id)
                ->where(function ($query) use ($email, $identifier, $username): void {
                    $query
                        ->whereRaw('LOWER(email) = ?', [$email])
                        ->orWhereRaw('UPPER(institutional_identifier) = ?', [$identifier])
                        ->orWhereRaw('LOWER(username) = ?', [$username]);
                })
                ->exists();

            if ($hasConflict) {
                // Audit the safe conflict category while leaving both the target and active account untouched.
                $this->auditLog->record($actor, 'user.archived_account_restore_blocked', $user, [
                    'account_type' => $type['key'],
                    'archived_user_id' => $user->id,
                    'excel_row' => (int) ($reference['row'] ?? 0),
                    'reason' => 'active_identity_conflict',
                    'result' => 'blocked',
                ]);

                return [
                    'restored' => false,
                    'reason' => 'An active account now uses this email, institutional identifier, or username.',
                ];
            }

            // Laravel restore clears deleted_at while preserving the original ID and every foreign-key relationship.
            $user->restore();
            $user->account_status = $user->password_setup_completed_at
                ? AccountStatus::Active->value
                : AccountStatus::PendingSetup->value;
            $user->save();

            $this->auditLog->record($actor, 'user.archived_account_restored', $user, [
                'account_type' => $type['key'],
                'excel_row' => (int) ($reference['row'] ?? 0),
                'restored_user_id' => $user->id,
                'result' => 'restored',
            ]);

            return [
                'restored' => true,
                'account' => $this->restoredAccountEntry($user, $reference),
            ];
        }, 3);
    }

    /**
     * Ensure the current user row still represents the identity classified during preview.
     *
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $type
     */
    private function matchesPreviewReference(User $user, array $reference, array $type): bool
    {
        // Account role and Applicant subtype are part of the authoritative template identity.
        return hash_equals(Str::lower((string) ($reference['email'] ?? '')), Str::lower($user->email))
            && hash_equals(
                Str::upper((string) ($reference['institutional_identifier'] ?? '')),
                Str::upper((string) $user->institutional_identifier),
            )
            && hash_equals(Str::lower((string) ($reference['username'] ?? '')), Str::lower((string) $user->username))
            && $user->role->value === (string) $type['role']
            && $user->applicant_type?->value === ($type['applicant_type'] ?? null);
    }

    /**
     * Return only safe account fields after restoration.
     *
     * @param  array<string, mixed>  $reference
     * @return array<string, mixed>
     */
    private function restoredAccountEntry(User $user, array $reference): array
    {
        return [
            'id' => $user->id,
            'row' => (int) ($reference['row'] ?? 0),
            'name' => $user->name,
            'institutional_identifier' => $user->institutional_identifier,
            'email' => $user->email,
            'role' => $user->displayRoleLabel(),
            'account_status' => $user->account_status,
        ];
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

            // Skip only physical Row 2 while the workbook's exact visible sentinel remains present.
            if ((int) $workbookRow['row'] === 2 && ($workbook['example_row_marked'] ?? false) === true) {
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
        $activeExistingAccounts = [];
        $archivedAccounts = [];
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
            ->select([
                'id',
                'name',
                'email',
                'institutional_identifier',
                'username',
                'role',
                'account_status',
                'password_setup_completed_at',
                'deleted_at',
            ])
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
                    $account = $emailOwner;
                    $entry = [
                        'id' => $account->id,
                        'row' => $line,
                        'name' => $account->name,
                        'institutional_identifier' => $account->institutional_identifier,
                        'email' => $account->email,
                        'username' => $account->username,
                        'role' => $account->displayRoleLabel(),
                        'account_status' => $account->account_status,
                        'archived_at' => $account->deleted_at?->toIso8601String(),
                        'reason' => $account->trashed()
                            ? 'This institutional identifier and email belong to an archived account. Restore the original account to reactivate it. No duplicate account was created.'
                            : 'This institutional identifier and email already belong to an active account. The account will not be created or overwritten.',
                    ];

                    // deleted_at is the sole authoritative classifier for active versus archived accounts.
                    if ($account->trashed()) {
                        $archivedAccounts[] = $entry;
                    } else {
                        $activeExistingAccounts[] = $entry;
                    }
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

        if ($duplicateRows !== [] || $activeExistingAccounts !== [] || $archivedAccounts !== []) {
            $warnings[] = 'Duplicate and existing accounts are skipped and never overwritten.';
        }

        // Adviser previews explain the escalation path without exposing any restoration control.
        if ($actor->role === UserRole::Adviser && $archivedAccounts !== []) {
            $warnings[] = 'An archived account was found. Contact the RES Lead to restore the original account.';
        }

        return [
            'total_count' => count($rows),
            'valid_count' => count($validRows),
            'invalid_count' => count($invalidRows),
            'duplicate_count' => count($duplicateRows),
            'active_existing_count' => count($activeExistingAccounts),
            'archived_count' => count($archivedAccounts),
            'restored_count' => 0,
            'restoration_conflict_count' => 0,
            'existing_count' => count($activeExistingAccounts) + count($archivedAccounts),
            'estimated_create_count' => count($validRows),
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'duplicate_rows' => $duplicateRows,
            'active_existing_accounts' => $activeExistingAccounts,
            'archived_accounts' => $archivedAccounts,
            'restored_accounts' => [],
            'restoration_conflicts' => [],
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

    /**
     * Store one current actor-owned preview and invalidate older abandoned previews.
     *
     * @param  array<string, mixed>  $type
     * @param  array<string, mixed>  $preview
     */
    private function storePreview(User $actor, array $type, array $preview): string
    {
        // A newly validated workbook replaces all earlier previews for this actor.
        $directory = 'imports/user-accounts/previews/'.$actor->id;
        Storage::disk('local')->delete(Storage::disk('local')->allFiles($directory));

        $token = (string) Str::uuid();
        $path = $this->previewPath($actor, $token);
        unset($preview['account_type'], $preview['preview_token']);

        // Persist server-authoritative valid and restoration categories in one private payload.
        $this->writePayload($path, [
            'actor_id' => $actor->id,
            'account_type' => $type['key'],
            'created_at' => now()->timestamp,
            'preview' => $preview,
        ]);

        return $token;
    }

    /**
     * Validate preview ownership and its fixed thirty-minute lifetime.
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertUsablePreview(User $actor, array $payload): void
    {
        // Actor mismatch and malformed private payloads use one non-enumerable authorization message.
        if ((int) ($payload['actor_id'] ?? 0) !== $actor->id
            || ! is_array($payload['preview'] ?? null)
            || ! is_string($payload['account_type'] ?? null)) {
            throw ValidationException::withMessages([
                'import_token' => 'This import preview is expired, replaced, confirmed, or does not belong to you.',
            ]);
        }

        // Expired files remain unusable even before asynchronous cleanup removes them.
        if (now()->timestamp - (int) ($payload['created_at'] ?? 0) > self::PREVIEW_TTL_MINUTES * 60) {
            throw ValidationException::withMessages([
                'import_token' => 'This import preview has expired. Validate the Excel file again.',
            ]);
        }
    }

    /**
     * Rehydrate a private preview with its authorized account-type definition and token.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function presentPreview(User $actor, string $token, array $payload): array
    {
        $type = $this->accountTypes->authorized($actor, (string) $payload['account_type']);
        $preview = $payload['preview'];
        $preview['account_type'] = $type;
        $preview['preview_token'] = $token;

        return $preview;
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
        // Missing or unreadable previews return a controlled validation result instead of a filesystem warning.
        $contents = is_file($path) ? @file_get_contents($path) : false;

        if (! is_string($contents)) {
            throw ValidationException::withMessages(['import_token' => 'The import preview could not be read.']);
        }

        try {
            // Malformed private preview JSON is treated as unusable and never exposes parser details.
            return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['import_token' => 'The import preview could not be read.']);
        }
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
