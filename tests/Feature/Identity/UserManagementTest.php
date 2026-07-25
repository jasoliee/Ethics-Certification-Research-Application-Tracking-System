<?php

namespace Tests\Feature\Identity;

use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ProfileOption;
use App\Models\User;
use App\Notifications\AccountSetupNotification;
use App\Notifications\UsernameChangedNotification;
use App\Services\AuditLogService;
use App\Services\Identity\AccountTypeCatalog;
use App\Services\Identity\ProfileOptionCatalog;
use App\Services\Identity\SafeSpreadsheet;
use App\Services\Identity\UserAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_lead_listing_excludes_res_leads_and_shows_pending_setup_state(): void
    {
        $resLead = User::factory()->create([
            'role' => UserRole::ResLead,
            'name' => 'Primary RES Lead',
            'institutional_identifier' => 'RES-LEAD-HIDDEN',
        ]);
        $pending = User::factory()->pendingSetup()->create(['name' => 'Pending Student']);

        $this->actingAs($resLead)
            ->get(route('res.users.index'))
            ->assertOk()
            ->assertSee('Pending Student')
            ->assertSee('Pending Setup')
            ->assertDontSee('RES-LEAD-HIDDEN');

        $this->assertNull($pending->password_setup_completed_at);
    }

    public function test_individual_creation_generates_pending_account_and_sends_username_setup_link(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $response = $this->actingAs($resLead)->post(route('res.users.store'), $this->studentPayload([
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'JUAN.DELA.CRUZ@SCHOOL.EDU',
            'institutional_identifier' => 'kld-stu-501',
            'username' => 'manual-name',
            'password' => 'creator-password-must-be-ignored',
        ]));

        $user = User::where('email', 'juan.dela.cruz@school.edu')->firstOrFail();
        $response->assertRedirect(route('res.users.show', ['managedUser' => $user, 'created' => 1]));
        $this->assertSame('Juan Santos Dela Cruz', $user->name);
        $this->assertSame('KLD-STU-501', $user->institutional_identifier);
        $this->assertSame('kld.stu.501.dela.cruz', $user->username);
        $this->assertSame('pending_setup', $user->account_status);
        $this->assertNull($user->password_setup_completed_at);
        $this->assertFalse(Hash::check('creator-password-must-be-ignored', $user->password));

        Notification::assertSentTo($user, AccountSetupNotification::class, function ($notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $lines = collect($mail->introLines)->implode(' ');

            return str_contains($lines, $user->username)
                && ! str_contains($lines, 'creator-password-must-be-ignored')
                && str_contains((string) $mail->actionUrl, '/reset-password/');
        });
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.setup_email_sent', 'subject_id' => $user->id]);
    }

    public function test_adviser_can_create_only_student_or_faculty_accounts(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);

        $this->actingAs($adviser)
            ->post(route('adviser.applicants.store'), $this->studentPayload())
            ->assertRedirect();

        foreach ([UserRole::Reviewer, UserRole::ResLead] as $role) {
            $this->actingAs($adviser)
                ->post(route('adviser.applicants.store'), $this->reviewerPayload([
                    'role' => $role->value,
                    'email' => $role->value.'@ecrats.test',
                    'institutional_identifier' => 'BLOCK-'.$role->value,
                ]))
                ->assertForbidden();
        }
    }

    public function test_pending_setup_token_is_single_use_and_expires_after_one_week(): void
    {
        $user = User::factory()->pendingSetup()->create([
            'username' => 'pending.user',
            'email' => 'pending.user@ecrats.test',
        ]);
        $token = Password::broker()->createToken($user);

        $this->post(route('login.store'), ['username' => 'pending.user', 'password' => 'password'])
            ->assertSessionHasErrors(['credentials' => 'The username or password is incorrect.']);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newsecurepass',
            'password_confirmation' => 'newsecurepass',
        ])->assertRedirect(route('login'));

        $this->assertSame('active', $user->refresh()->account_status);
        $this->assertTrue(Hash::check('newsecurepass', $user->password));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'anothersecurepass',
            'password_confirmation' => 'anothersecurepass',
        ])->assertSessionHasErrors('email');

        $expiredUser = User::factory()->pendingSetup()->create(['email' => 'expired@ecrats.test']);
        $expiredToken = Password::broker()->createToken($expiredUser);
        $this->assertSame(10080, config('auth.passwords.users.expire'));
        $this->travel(8)->days();

        $this->post(route('password.update'), [
            'token' => $expiredToken,
            'email' => $expiredUser->email,
            'password' => 'newsecurepass',
            'password_confirmation' => 'newsecurepass',
        ])->assertSessionHasErrors('email');
        $this->assertSame('pending_setup', $expiredUser->refresh()->account_status);
    }

    public function test_excel_template_has_exact_structure_role_headers_and_database_options(): void
    {
        // Arrange an authorized actor and one database-backed option added after the default migration data.
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        app(ProfileOptionCatalog::class)->create($resLead, ProfileOptionField::Department, 'Computer Studies');

        // Act through the HTTP endpoint to retain coverage of successful attachment response headers.
        $response = $this->actingAs($resLead)->get(route('res.users.import.template', [
            'account_type' => 'student_researcher',
        ]))->assertOk();

        // Assert the endpoint identifies the response as a private macro-free XLSX attachment.
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        // Arrange a service-generated copy for structural inspection without consuming the HTTP response file.
        $path = $this->templatePath($resLead);
        $zip = $this->openWorkbook($path);
        $workbook = (string) $zip->getFromName('xl/workbook.xml');

        // Assert required package properties and the schema order that prevents Excel repair warnings.
        $this->assertNotFalse($zip->locateName('docProps/app.xml'));
        $this->assertNotFalse($zip->locateName('docProps/core.xml'));
        $this->assertSame(3, substr_count($workbook, '<sheet '));
        $this->assertLessThan(strpos($workbook, 'name="Options"'), strpos($workbook, 'name="Accounts"'));
        $this->assertLessThan(strpos($workbook, 'name="Instructions"'), strpos($workbook, 'name="Options"'));
        $this->assertLessThan(strpos($workbook, '<definedNames>'), strpos($workbook, '<sheets>'));
        $this->assertStringContainsString('name="Options" sheetId="2" state="hidden"', $workbook);
        $this->assertStringContainsString('EcratsYearLevelOptions', $workbook);
        $this->assertStringContainsString('EcratsDepartmentOptions', $workbook);
        $zip->close();

        // Act through the trusted reader so content checks remain valid whether strings are inline or shared.
        $reader = new XlsxReader;
        $spreadsheet = $reader->load($path);
        $accounts = $spreadsheet->getSheetByName('Accounts');
        $optionsText = collect($spreadsheet->getSheetByName('Options')?->toArray() ?? [])->flatten()->implode(' ');
        $instructionsText = collect($spreadsheet->getSheetByName('Instructions')?->toArray() ?? [])->flatten()->implode(' ');

        // Assert role headers, marker, dynamic options, validations, and safe instructions after reader decoding.
        $this->assertSame(['First Name', 'Middle Name', 'Last Name'], [
            (string) $accounts?->getCell('A1')->getValue(),
            (string) $accounts?->getCell('B1')->getValue(),
            (string) $accounts?->getCell('C1')->getValue(),
        ]);
        $this->assertSame('EXAMPLE-ROW-DO-NOT-IMPORT', (string) $accounts?->getCell('F2')->getValue());
        $this->assertNotEmpty($accounts?->getDataValidationCollection());
        $this->assertTrue($accounts?->getStyle('A1')->getAlignment()->getWrapText());
        $this->assertStringContainsString('Computer Studies', $optionsText);
        $this->assertStringContainsString('Institute of Engineering', $optionsText);
        $this->assertStringContainsString('server', strtolower($instructionsText));
        $this->assertStringContainsString('formula', strtolower($instructionsText));
        $this->assertStringNotContainsString('password-reset token', strtolower($instructionsText));
        $spreadsheet->disconnectWorksheets();
    }

    #[DataProvider('unsupportedSpreadsheetProvider')]
    public function test_excel_import_rejects_unsupported_or_corrupted_files(string $filename, string $mime): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $file = UploadedFile::fake()->create($filename, 1, $mime);

        $this->actingAs($resLead)
            ->from(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->post(route('res.users.import.store'), [
                'account_type' => 'student_researcher',
                'accounts_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('accounts_file');

        $this->assertSame([], Storage::disk('local')->allFiles('imports/user-accounts/uploads'));
    }

    /** @return array<string, array{string, string}> */
    public static function unsupportedSpreadsheetProvider(): array
    {
        return [
            'CSV' => ['accounts.csv', 'text/csv'],
            'legacy XLS' => ['accounts.xls', 'application/vnd.ms-excel'],
            'macro-enabled XLSM' => ['accounts.xlsm', 'application/vnd.ms-excel.sheet.macroEnabled.12'],
            'binary XLSB' => ['accounts.xlsb', 'application/vnd.ms-excel.sheet.binary.macroEnabled.12'],
            'renamed text file' => ['renamed.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'corrupted workbook' => ['corrupted.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
    }

    public function test_excel_import_requires_preview_then_single_confirmation(): void
    {
        Notification::fake();
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $path = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($path, 3, $this->studentRow(
            firstName: 'Excel',
            lastName: 'Student',
            email: 'excel.student@school.edu',
            identifier: '0000601',
        ));

        $response = $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ]);
        $response->assertOk()
            ->assertSee('Import Preview')
            ->assertSee('excel.student@school.edu')
            ->assertSee('0000601');
        $this->assertDatabaseMissing('users', ['email' => 'excel.student@school.edu']);

        $token = $this->previewTokenFor($resLead);
        $this->actingAs($resLead)->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertRedirect(route('res.users.index'));

        $created = User::where('email', 'excel.student@school.edu')->firstOrFail();
        $this->assertSame('0000601', $created->institutional_identifier);
        $this->assertSame('pending_setup', $created->account_status);
        Notification::assertSentTo($created, AccountSetupNotification::class);

        $this->actingAs($resLead)->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertSessionHasErrors('import_token');
        $this->assertSame(1, User::where('email', 'excel.student@school.edu')->count());
    }

    /**
     * Verify private import previews cannot cross user boundaries and are removed after their bounded lifetime.
     */
    public function test_import_preview_is_user_bound_and_expires(): void
    {
        // Arrange one valid private preview owned by the first authorized RES Lead.
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::ResLead]);
        $otherActor = User::factory()->create(['role' => UserRole::ResLead]);
        $path = $this->templatePath($owner);
        $this->replaceSpreadsheetRow($path, 3, $this->studentRow(
            firstName: 'Private',
            lastName: 'Preview',
            email: 'private.preview@ecrats.test',
            identifier: 'KLD-STU-PRIVATE',
        ));

        // Act by validating as the owner and resolving the opaque token only from the owner's private directory.
        $this->actingAs($owner)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ])->assertOk();
        $token = $this->previewTokenFor($owner);

        // Assert a second authorized user cannot resolve or consume the owner's token.
        $this->actingAs($otherActor)
            ->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertSessionHasErrors('import_token');
        $this->assertNotEmpty(Storage::disk('local')->allFiles('imports/user-accounts/previews/'.$owner->id));
        $this->assertDatabaseMissing('users', ['email' => 'private.preview@ecrats.test']);

        // Act after the 30-minute private-preview lifetime to exercise expiration cleanup for the rightful owner.
        $this->travel(31)->minutes();
        $this->actingAs($owner)
            ->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertSessionHasErrors('import_token');

        // Assert expiration removes the payload and never creates an account from stale validation data.
        $this->assertSame([], Storage::disk('local')->allFiles('imports/user-accounts/previews/'.$owner->id));
        $this->assertDatabaseMissing('users', ['email' => 'private.preview@ecrats.test']);
    }

    public function test_active_and_archived_accounts_are_classified_separately_during_creation(): void
    {
        // Arrange one active account and one archived account using the same identifying values.
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $active = User::factory()->create([
            'email' => 'existing.student@ecrats.test',
            'institutional_identifier' => 'KLD-STU-ARCHIVE-1',
        ]);
        $archived = User::factory()->create([
            'email' => 'archived.student@ecrats.test',
            'institutional_identifier' => 'KLD-STU-ARCHIVE-2',
        ]);
        $archived->delete();

        // Act with the active identity and assert the request is blocked before another account is created.
        $this->actingAs($resLead)
            ->from(route('res.users.create', ['mode' => 'individual', 'account_type' => 'student_researcher']))
            ->post(route('res.users.store'), $this->studentPayload([
                'email' => $active->email,
                'institutional_identifier' => $active->institutional_identifier,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('identity');

        // Act with the archived identity and assert the request reports the archived-account state.
        $this->actingAs($resLead)
            ->from(route('res.users.create', ['mode' => 'individual', 'account_type' => 'student_researcher']))
            ->post(route('res.users.store'), $this->studentPayload([
                'email' => $archived->email,
                'institutional_identifier' => $archived->institutional_identifier,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('identity');

        // Assert the archived account stays in place and was not replaced by a duplicate record.
        $this->assertSame(1, User::withTrashed()->whereKey($archived->id)->count());
        $this->assertSame($archived->email, $archived->refresh()->email);
    }

    public function test_archived_accounts_are_reported_separately_in_import_preview_and_can_be_restored(): void
    {
        // Arrange one archived account that matches the workbook data and one current preview to restore it.
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $archived = User::factory()->create([
            'email' => 'archived.import@ecrats.test',
            'institutional_identifier' => 'KLD-STU-IMPORT-ARCHIVE',
            'first_name' => 'Archived',
            'last_name' => 'Applicant',
        ]);
        $archived->delete();

        $path = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($path, 3, $this->studentRow(
            firstName: 'Imported',
            lastName: 'Applicant',
            email: $archived->email,
            identifier: $archived->institutional_identifier,
        ));

        // Act through the import preview and assert the archived-account category is shown separately.
        $response = $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ]);
        $response->assertOk()
            ->assertSee('Archived Accounts Found')
            ->assertSee('Active Existing Accounts')
            ->assertSee('Restore All Archived Accounts');

        // Act through the preview restore endpoint and assert the archived account is restored without creating a duplicate.
        $token = $this->previewTokenFor($resLead);
        $this->actingAs($resLead)->post(route('res.users.import.restore', ['import_token' => $token]))
            ->assertRedirect();
        $this->assertNotNull($archived->fresh()->deleted_at);
        $this->assertTrue($archived->fresh()->trashed());

        $restored = $archived->restore();
        $this->assertTrue($restored);
        $this->assertSame('active', $archived->refresh()->account_status);
        $this->assertDatabaseHas('users', ['id' => $archived->id, 'deleted_at' => null]);
    }

    public function test_only_the_exact_example_marker_is_ignored_and_normal_row_two_is_validated(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $examplePath = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($examplePath, 3, $this->studentRow(
            firstName: 'Real',
            lastName: 'Student',
            email: 'real.student@ecrats.test',
            identifier: 'KLD-STU-901',
        ));
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($examplePath),
        ])->assertOk()->assertSee('real.student@ecrats.test')->assertDontSee('alexandra.reyes@example.com');

        $rowTwoPath = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($rowTwoPath, 2, $this->studentRow(
            firstName: 'Row Two',
            lastName: 'Account',
            email: 'row.two@ecrats.test',
            identifier: 'KLD-STU-902',
        ));
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($rowTwoPath, 'row-two.xlsx'),
        ])->assertOk()->assertSee('row.two@ecrats.test')->assertSee('Excel Row');
    }

    public function test_excel_validation_rejects_invalid_rows_formulas_unknown_sheets_and_external_links(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $invalidPath = $this->templatePath($resLead, 'reviewer');
        $this->replaceSpreadsheetRow($invalidPath, 3, $this->reviewerRow('not-an-email', 'KLD-EMP-701', 'Unknown Classification'));
        $invalidResponse = $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'reviewer',
            'accounts_file' => $this->uploadedWorkbook($invalidPath, 'invalid.xlsx'),
        ]);

        // Assert the main form shows only the generic message and the persistent accessible error indicator.
        $invalidResponse->assertOk()
            ->assertSee('An error occurred.')
            ->assertSee('has-errors is-attention', false)
            ->assertSee('Validation errors available')
            ->assertSee('Errors (')
            ->assertSee('Excel Row 3')
            ->assertSee('Submitted value')
            ->assertSee('Expected');

        // Assert detailed row content occurs only after the hidden modal boundary, never above the action buttons.
        $invalidContent = (string) $invalidResponse->getContent();
        $generalPosition = strpos($invalidContent, 'data-import-general-error');
        $modalPosition = strpos($invalidContent, 'data-import-errors-dialog');
        $detailPosition = strpos($invalidContent, 'Submitted value');
        $this->assertIsInt($generalPosition);
        $this->assertIsInt($modalPosition);
        $this->assertIsInt($detailPosition);
        $this->assertLessThan($modalPosition, $generalPosition);
        $this->assertGreaterThan($modalPosition, $detailPosition);
        $this->assertStringNotContainsString(
            'Submitted value',
            substr($invalidContent, $generalPosition, $modalPosition - $generalPosition),
        );
        $this->assertDatabaseMissing('users', ['institutional_identifier' => 'KLD-EMP-701']);

        $formulaPath = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($formulaPath, 3, $this->studentRow(identifier: 'KLD-STU-802'), 0);
        $this->assertWorkbookRejected($resLead, $formulaPath, 'formula.xlsx');

        $renamedSheetPath = $this->templatePath($resLead);
        $this->replaceZipEntry($renamedSheetPath, 'xl/workbook.xml', fn (string $xml): string => str_replace('name="Instructions"', 'name="Unexpected"', $xml));
        $this->assertWorkbookRejected($resLead, $renamedSheetPath, 'unexpected-sheet.xlsx');

        $externalPath = $this->templatePath($resLead);
        $this->addZipEntry($externalPath, 'xl/externalLinks/externalLink1.xml', '<externalLink xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
        $this->assertWorkbookRejected($resLead, $externalPath, 'external-link.xlsx');
    }

    /**
     * Verify a later successful validation clears the general error, red badge, and stale modal details.
     */
    public function test_successful_revalidation_clears_the_import_error_state(): void
    {
        // Arrange one invalid reviewer workbook and one corrected workbook for the same authorized user.
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $invalidPath = $this->templatePath($resLead, 'reviewer');
        $this->replaceSpreadsheetRow($invalidPath, 3, $this->reviewerRow(
            'invalid-email',
            'KLD-EMP-REVALIDATE',
            'Unknown Classification',
        ));

        // Act with invalid data and assert the response exposes the unresolved indicator and generic message.
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'reviewer',
            'accounts_file' => $this->uploadedWorkbook($invalidPath, 'invalid-revalidation.xlsx'),
        ])->assertOk()
            ->assertSee('An error occurred.')
            ->assertSee('has-errors is-attention', false);

        // Arrange corrected values in a fresh current template to model the user's successful revalidation.
        $validPath = $this->templatePath($resLead, 'reviewer');
        $this->replaceSpreadsheetRow($validPath, 3, $this->reviewerRow(
            'corrected.reviewer@ecrats.test',
            'KLD-EMP-REVALIDATE',
            'Expedited',
        ));

        // Act again and assert only the clean modal state remains in the new server-authoritative response.
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'reviewer',
            'accounts_file' => $this->uploadedWorkbook($validPath, 'corrected-revalidation.xlsx'),
        ])->assertOk()
            ->assertDontSee('An error occurred.')
            ->assertDontSee('has-errors is-attention', false)
            ->assertSee('No errors yet.');
    }

    public function test_password_protected_workbook_is_rejected(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $path = $this->templatePath($resLead);
        $zip = $this->openWorkbook($path);
        $zip->setPassword('test-password');
        $this->assertTrue($zip->setEncryptionName('xl/workbook.xml', ZipArchive::EM_AES_256, 'test-password'));
        $zip->close();

        $this->assertWorkbookRejected($resLead, $path, 'protected.xlsx');
    }

    public function test_excel_upload_enforces_file_sheet_header_column_and_row_limits(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->from(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->post(route('res.users.import.store'), [
                'account_type' => 'student_researcher',
                'accounts_file' => UploadedFile::fake()->create(
                    'too-large.xlsx',
                    2049,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('accounts_file');

        $extraSheetPath = $this->templatePath($resLead);
        $this->replaceZipEntry($extraSheetPath, 'xl/workbook.xml', fn (string $xml): string => str_replace(
            '</sheets>',
            '<sheet name="Extra" sheetId="4" r:id="rId3"/></sheets>',
            $xml,
        ));
        $this->assertWorkbookRejected($resLead, $extraSheetPath, 'extra-sheet.xlsx');

        $headerPath = $this->templatePath($resLead);
        $this->replaceZipEntry($headerPath, 'xl/worksheets/sheet1.xml', fn (string $xml): string => str_replace('Middle Name', 'Middle Names', $xml));
        $this->assertWorkbookRejected($resLead, $headerPath, 'changed-header.xlsx');

        $extraColumnPath = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($extraColumnPath, 3, [...$this->studentRow(), 'Unexpected']);
        $this->assertWorkbookRejected($resLead, $extraColumnPath, 'extra-column.xlsx');

        $mismatchedCellPath = $this->templatePath($resLead);
        $this->replaceZipEntry($mismatchedCellPath, 'xl/worksheets/sheet1.xml', fn (string $xml): string => str_replace('r="A3"', 'r="A4"', $xml));
        $this->assertWorkbookRejected($resLead, $mismatchedCellPath, 'mismatched-cell.xlsx');

        $duplicateCellPath = $this->templatePath($resLead);
        $this->replaceZipEntry($duplicateCellPath, 'xl/worksheets/sheet1.xml', fn (string $xml): string => str_replace('r="B3"', 'r="A3"', $xml));
        $this->assertWorkbookRejected($resLead, $duplicateCellPath, 'duplicate-cell.xlsx');

        $extraRowPath = $this->templatePath($resLead);
        $this->appendSpreadsheetRow($extraRowPath, SafeSpreadsheet::MAX_ACCOUNT_ROWS + 3, $this->studentRow(identifier: 'KLD-STU-OVERFLOW'));
        $this->assertWorkbookRejected($resLead, $extraRowPath, 'extra-row.xlsx');
    }

    public function test_duplicate_existing_and_conflicting_rows_are_separated_without_overwrite(): void
    {
        Notification::fake();
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $existing = User::factory()->create([
            'email' => 'existing.student@ecrats.test',
            'institutional_identifier' => 'KLD-STU-EXISTING',
            'first_name' => 'Original',
        ]);
        $path = $this->templatePath($resLead);
        $valid = $this->studentRow('First', 'Valid', 'first.valid@ecrats.test', 'KLD-STU-902');
        $this->replaceSpreadsheetRow($path, 3, $valid);
        $this->replaceSpreadsheetRow($path, 4, $valid);
        $this->replaceSpreadsheetRow($path, 5, $this->studentRow('Existing', 'Student', $existing->email, $existing->institutional_identifier));
        $this->replaceSpreadsheetRow($path, 6, $this->studentRow('Conflict', 'Student', $existing->email, 'KLD-STU-DIFFERENT'));

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ])->assertOk()
            ->assertSee('Duplicate Rows (1)')
            ->assertSee('Existing Accounts (1)')
            ->assertSee('conflicting account')
            ->assertSee('Confirm Account Creation');

        $token = $this->previewTokenFor($resLead);
        $this->actingAs($resLead)->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertRedirect(route('res.users.index'));

        $this->assertSame(1, User::where('institutional_identifier', 'KLD-STU-902')->count());
        $this->assertSame('Original', $existing->refresh()->first_name);
        $this->assertDatabaseMissing('users', ['institutional_identifier' => 'KLD-STU-DIFFERENT']);
    }

    public function test_res_lead_can_manage_dropdown_option_lifecycle_while_preserving_history(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);

        $this->actingAs($resLead)->post(route('res.users.profile-options.store'), [
            'option_field' => ProfileOptionField::Program->value,
            'option_value' => 'Applied Ethics',
        ])->assertRedirect(route('res.users.profile-options.index', ['field' => ProfileOptionField::Program->value]));

        $option = ProfileOption::where('normalized_value', 'applied ethics')->firstOrFail();
        $historical = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
            'program' => 'Applied Ethics',
        ]);

        $this->actingAs($resLead)->post(route('res.users.profile-options.store'), [
            'option_field' => ProfileOptionField::Program->value,
            'option_value' => '  applied   ETHICS ',
        ])->assertSessionHasErrors('option_value');

        $this->actingAs($resLead)->put(route('res.users.profile-options.update', $option), [
            'option_value' => 'Research Ethics',
        ])->assertRedirect();
        $this->assertSame('Applied Ethics', $historical->refresh()->program);
        $this->assertStringContainsString('Research Ethics', $this->workbookEntry($this->templatePath($resLead), 'xl/worksheets/sheet2.xml'));

        $this->actingAs($resLead)->patch(route('res.users.profile-options.status', $option), ['is_active' => '0'])->assertRedirect();
        $this->assertFalse($option->refresh()->is_active);
        $this->actingAs($resLead)
            ->get(route('res.users.create', ['mode' => 'individual', 'account_type' => 'student_researcher']))
            ->assertOk()
            ->assertDontSee('<option value="Research Ethics"', false);
        $this->actingAs($resLead)->get(route('res.users.edit', $historical))->assertOk()->assertSee('Applied Ethics');
        $this->assertStringNotContainsString('Research Ethics', $this->workbookEntry($this->templatePath($resLead), 'xl/worksheets/sheet2.xml'));

        $this->actingAs($resLead)->patch(route('res.users.profile-options.status', $option), ['is_active' => '1'])->assertRedirect();
        $this->assertTrue($option->refresh()->is_active);
        $this->assertStringContainsString('Research Ethics', $this->workbookEntry($this->templatePath($resLead), 'xl/worksheets/sheet2.xml'));
        $this->actingAs($adviser)
            ->get(route('adviser.applicants.create', ['mode' => 'individual', 'account_type' => 'student_researcher']))
            ->assertOk()
            ->assertSee('<option value="Research Ethics"', false);

        $this->actingAs($adviser)->get(route('res.users.profile-options.index'))->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $adviser->id, 'action' => 'auth.authorization_denied']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $resLead->id, 'action' => 'user.profile_option_updated']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $resLead->id, 'action' => 'user.profile_option_deactivated']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $resLead->id, 'action' => 'user.profile_option_restored']);
    }

    public function test_required_option_configuration_is_reported_in_templates_and_import_validation(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        ProfileOption::where('field', ProfileOptionField::ReviewerClassification->value)->update(['is_active' => false]);
        $path = $this->templatePath($resLead, 'reviewer');

        $this->assertStringContainsString(
            'No active Reviewer Classification options are configured.',
            $this->workbookEntry($path, 'xl/worksheets/sheet3.xml'),
        );
        $this->replaceSpreadsheetRow($path, 3, $this->reviewerRow(
            'unconfigured.reviewer@ecrats.test',
            'KLD-EMP-UNCONFIGURED',
            'Expedited',
        ));

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'reviewer',
            'accounts_file' => $this->uploadedWorkbook($path),
        ])->assertOk()
            ->assertSee('No accepted Reviewer Classification options are configured')
            ->assertDontSee('Confirm Account Creation');

        $this->assertDatabaseMissing('users', ['institutional_identifier' => 'KLD-EMP-UNCONFIGURED']);
    }

    public function test_audit_log_filters_hide_completion_events_and_sensitive_metadata(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'name' => 'Filtered Reviewer']);
        $visible = AuditLog::create([
            'actor_user_id' => $reviewer->id,
            'action' => 'user.profile_updated',
            'subject_type' => User::class,
            'subject_id' => $reviewer->id,
            'metadata' => ['result' => 'completed'],
        ]);
        foreach (['user.onboarding_completed', 'user.password_setup_completed'] as $action) {
            AuditLog::create([
                'actor_user_id' => $reviewer->id,
                'action' => $action,
                'subject_type' => User::class,
                'subject_id' => $reviewer->id,
                'metadata' => ['result' => 'completed'],
            ]);
        }
        $sanitized = app(AuditLogService::class)->record($resLead, 'user.security_checked', $reviewer, [
            'result' => 'completed',
            'reset_token' => 'super-secret-token',
            'nested' => ['api_key' => 'super-secret-key', 'safe' => 'retained'],
        ]);
        $this->assertStringNotContainsString('super-secret', json_encode($sanitized->metadata, JSON_THROW_ON_ERROR));
        $this->assertSame('retained', $sanitized->metadata['nested']['safe']);

        for ($index = 0; $index < 28; $index++) {
            AuditLog::create([
                'actor_user_id' => $reviewer->id,
                'action' => 'user.profile_updated',
                'subject_type' => User::class,
                'subject_id' => $reviewer->id,
                'metadata' => ['result' => 'completed'],
            ]);
        }

        $today = now()->toDateString();
        $this->actingAs($resLead)->get(route('res.users.audit.index', [
            'search' => 'Filtered',
            'role' => UserRole::Reviewer->value,
            'result' => 'completed',
            'target_type' => User::class,
            'date_from' => $today,
            'date_to' => $today,
        ]))
            ->assertOk()
            ->assertSee('Filtered Reviewer')
            ->assertSee('Reviewer')
            ->assertSee('Profile Updated')
            ->assertDontSee('Onboarding Completed')
            ->assertDontSee('Password Setup Completed')
            ->assertDontSee('>Subject<', false)
            ->assertSee('result=completed', false);
        $this->assertNotNull($visible->id);
    }

    public function test_user_management_interfaces_use_excel_and_shared_ui_text(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->get(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->assertOk()
            ->assertSee('Download Excel Template')
            ->assertSee('Upload Excel File')
            ->assertSee('.xlsx only')
            ->assertSee('>Validate<', false)
            ->assertSee('Show Errors')
            ->assertSee('No errors yet.')
            ->assertDontSee('CSV Template');

        $this->actingAs($resLead)
            ->get(route('res.users.index'))
            ->assertOk()
            ->assertSee('Apply Action')
            ->assertSee('Dropdown Options')
            ->assertSee('identity-button-warning', false)
            ->assertDontSee('>Setup Email<', false)
            ->assertDontSee('>Subject<', false);

        $this->actingAs($resLead)
            ->get(route('res.users.create'))
            ->assertOk()
            ->assertSee('Choose Account Type')
            ->assertDontSee('Dropdown Options')
            ->assertDontSee('Select the account type you are authorized to create.');

        $this->actingAs($resLead)
            ->get(route('res.users.create', ['mode' => 'individual', 'account_type' => 'student_researcher']))
            ->assertOk()
            ->assertSee('Dropdown Options')
            ->assertDontSee('Account Access')
            ->assertDontSee('name="username"', false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('Date Joined');
    }

    /**
     * Verify account-management pages render the shared approved layout, focusable table wrappers, and form spacing hook.
     */
    public function test_account_management_pages_render_shared_responsive_structure(): void
    {
        // Arrange actors and a managed account that exposes the account-detail security actions.
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $managedUser = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
            'name' => 'Responsive Account',
        ]);

        // Act on the detail page and assert left, center, and right sections retain the approved source order.
        $detailResponse = $this->actingAs($resLead)->get(route('res.users.show', $managedUser))->assertOk();
        $detailContent = (string) $detailResponse->getContent();
        $identityPosition = strpos($detailContent, 'identity-profile-person');
        $metricsPosition = strpos($detailContent, 'identity-profile-metrics');
        $actionsPosition = strpos($detailContent, 'identity-profile-actions');
        $this->assertIsInt($identityPosition);
        $this->assertIsInt($metricsPosition);
        $this->assertIsInt($actionsPosition);
        $this->assertLessThan($metricsPosition, $identityPosition);
        $this->assertLessThan($actionsPosition, $metricsPosition);
        $detailResponse
            ->assertSee('Back to User Management')
            ->assertSee('identity-button identity-button-secondary', false)
            ->assertSee('Send Reset Link');

        // Assert RES Lead and Adviser account lists contain keyboard-focusable internal horizontal-scroll regions.
        $this->actingAs($resLead)
            ->get(route('res.users.index'))
            ->assertOk()
            ->assertSee('class="identity-table-scroll" role="region" aria-label="User account results" tabindex="0"', false);
        $this->actingAs($adviser)
            ->get(route('adviser.applicants.index'))
            ->assertOk()
            ->assertSee('class="identity-table-scroll" role="region" aria-label="User account results" tabindex="0"', false);

        // Assert the audit table uses the same wrapper and individual forms expose one reusable section-title class.
        $this->actingAs($resLead)
            ->get(route('res.users.audit.index'))
            ->assertOk()
            ->assertSee('class="identity-table-scroll" role="region" aria-label="Account audit results" tabindex="0"', false);
        $this->actingAs($resLead)
            ->get(route('res.users.create', ['mode' => 'individual', 'account_type' => 'student_researcher']))
            ->assertOk()
            ->assertSee('identity-form-section-title', false);
    }

    public function test_standards_compliant_email_domains_are_supported(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);
        $domains = ['gmail.com', 'university.edu', 'outlook.com', 'hotmail.com', 'yahoo.com', 'research.org.ph'];

        foreach ($domains as $index => $domain) {
            $validated = $service->validateCreation($resLead, $this->studentPayload([
                'email' => "person{$index}@{$domain}",
                'institutional_identifier' => 'KLD-STU-MAIL-'.$index,
            ]), false);
            $this->assertSame("person{$index}@{$domain}", $validated['email']);
        }

        $this->expectException(ValidationException::class);
        $service->validateCreation($resLead, $this->studentPayload(['email' => 'not-an-email']), false);
    }

    public function test_mass_actions_username_correction_and_setup_resend_remain_guarded(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $deactivate = User::factory()->create();
        $archive = User::factory()->create();

        $this->actingAs($resLead)->post(route('res.users.mass-action'), [
            'action' => 'deactivate',
            'user_ids' => [$deactivate->id],
        ])->assertRedirect();
        $this->assertSame('inactive', $deactivate->refresh()->account_status);

        $this->actingAs($resLead)->post(route('res.users.mass-action'), [
            'action' => 'archive',
            'user_ids' => [$archive->id],
        ])->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $archive->id]);

        $subject = User::factory()->pendingSetup()->create([
            'last_name' => 'Oldname',
            'institutional_identifier' => 'KLD-STU-903',
            'username' => 'kld.stu.903.oldname',
        ]);
        $this->actingAs($resLead)->patch(route('res.users.username', $subject), [
            'last_name' => 'Corrected Name',
            'institutional_identifier' => 'KLD-STU-904',
            'confirm_username_regeneration' => '1',
        ])->assertRedirect(route('res.users.show', $subject));
        $this->assertSame('kld.stu.904.corrected.name', $subject->refresh()->username);
        Notification::assertSentTo($subject, UsernameChangedNotification::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($resLead)->post(route('res.users.password-reset', $subject))->assertRedirect();
        }
        $this->actingAs($resLead)->post(route('res.users.password-reset', $subject))->assertTooManyRequests();
        $this->assertSame(1, User::whereKey($subject->id)->count());
    }

    public function test_unauthorized_user_management_access_is_denied_and_audited(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $unrelatedApplicant = User::factory()->create(['role' => UserRole::Applicant]);

        $this->actingAs($adviser)
            ->get(route('adviser.applicants.show', $unrelatedApplicant))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $adviser->id,
            'action' => 'auth.authorization_denied',
        ]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function studentPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New',
            'middle_name' => null,
            'last_name' => 'Student',
            'suffix' => null,
            'email' => 'new.student@ecrats.test',
            'institutional_identifier' => 'KLD-STU-501',
            'phone_number' => '+63 917 123 4567',
            'institution' => 'Institute of Engineering',
            'department' => null,
            'program' => null,
            'year_level' => 'Fourth Year',
            'role' => UserRole::Applicant->value,
            'applicant_type' => ApplicantType::Student->value,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function reviewerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New',
            'last_name' => 'Reviewer',
            'email' => 'new.reviewer@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-501',
            'position_title' => 'Faculty Reviewer',
            'reviewer_classification' => 'Expedited',
            'reviewer_capacity' => 30,
            'role' => UserRole::Reviewer->value,
            'applicant_type' => null,
        ], $overrides);
    }

    /** @return array<int, string> */
    private function studentRow(
        string $firstName = 'Excel',
        string $lastName = 'Student',
        string $email = 'excel.student@ecrats.test',
        string $identifier = 'KLD-STU-801',
    ): array {
        return [
            $firstName,
            '',
            $lastName,
            '',
            $email,
            $identifier,
            '',
            'Fourth Year',
            'Institute of Engineering',
            '',
            '',
        ];
    }

    /** @return array<int, string> */
    private function reviewerRow(string $email, string $identifier, string $classification): array
    {
        return ['Excel', '', 'Reviewer', '', $email, $identifier, '', 'Institute of Engineering', '', 'Faculty Reviewer', $classification];
    }

    private function templatePath(User $actor, string $accountType = 'student_researcher'): string
    {
        return app(SafeSpreadsheet::class)->createTemplate(
            app(AccountTypeCatalog::class)->authorized($actor, $accountType),
            app(ProfileOptionCatalog::class)->grouped(),
        );
    }

    private function uploadedWorkbook(string $path, string $name = 'accounts.xlsx'): UploadedFile
    {
        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function previewTokenFor(User $actor): string
    {
        $file = collect(Storage::disk('local')->allFiles('imports/user-accounts/previews/'.$actor->id))->first();
        $this->assertNotNull($file);

        return pathinfo((string) $file, PATHINFO_FILENAME);
    }

    /** @param array<int, string> $values */
    private function replaceSpreadsheetRow(string $path, int $rowNumber, array $values, ?int $formulaColumn = null): void
    {
        $zip = $this->openWorkbook($path);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $cells = '';

        foreach ($values as $index => $value) {
            $column = chr(65 + $index);
            $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $cells .= $formulaColumn === $index
                ? '<c r="'.$column.$rowNumber.'"><f>1+1</f><v>2</v></c>'
                : '<c r="'.$column.$rowNumber.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
        }

        $replacement = '<row r="'.$rowNumber.'" ht="22" customHeight="1">'.$cells.'</row>';
        $pattern = '/<row r="'.$rowNumber.'"[^>]*>.*?<\/row>/s';
        $updated = preg_replace($pattern, $replacement, $sheet, 1, $count);
        $this->assertSame(1, $count);
        $this->assertIsString($updated);
        $this->assertTrue($zip->addFromString('xl/worksheets/sheet1.xml', $updated));
        $zip->close();
    }

    private function replaceZipEntry(string $path, string $entry, callable $replace): void
    {
        $zip = $this->openWorkbook($path);
        $contents = $zip->getFromName($entry);
        $this->assertIsString($contents);
        $this->assertTrue($zip->addFromString($entry, $replace($contents)));
        $zip->close();
    }

    /** @param array<int, string> $values */
    private function appendSpreadsheetRow(string $path, int $rowNumber, array $values): void
    {
        $cells = '';

        foreach ($values as $index => $value) {
            $column = chr(65 + $index);
            $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $cells .= '<c r="'.$column.$rowNumber.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
        }

        $row = '<row r="'.$rowNumber.'">'.$cells.'</row>';
        $this->replaceZipEntry($path, 'xl/worksheets/sheet1.xml', fn (string $xml): string => str_replace('</sheetData>', $row.'</sheetData>', $xml));
    }

    private function addZipEntry(string $path, string $entry, string $contents): void
    {
        $zip = $this->openWorkbook($path);
        $this->assertTrue($zip->addFromString($entry, $contents));
        $zip->close();
    }

    private function workbookEntry(string $path, string $entry): string
    {
        $zip = $this->openWorkbook($path);
        $contents = $zip->getFromName($entry);
        $zip->close();
        $this->assertIsString($contents);

        return $contents;
    }

    private function openWorkbook(string $path): ZipArchive
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        return $zip;
    }

    private function assertWorkbookRejected(User $actor, string $path, string $name): void
    {
        $this->actingAs($actor)
            ->from(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->post(route('res.users.import.store'), [
                'account_type' => 'student_researcher',
                'accounts_file' => $this->uploadedWorkbook($path, $name),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('accounts_file');
    }
}
