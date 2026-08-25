<?php

namespace Tests\Feature\Identity;

use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\ProfileOption;
use App\Models\ResearchApplication;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
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

    public function test_adviser_applicant_header_and_role_filter_match_the_visible_account_scope(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $student = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
            'created_by_user_id' => $adviser,
            'name' => 'Visible Student Researcher',
        ]);
        $faculty = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Faculty,
            'created_by_user_id' => $adviser,
            'name' => 'Visible Faculty Researcher',
        ]);

        $response = $this->actingAs($adviser)->get(route('adviser.applicants.index'))
            ->assertOk()
            ->assertSee('Applicants')
            ->assertSee('Filter by Role')
            ->assertSee('Student Researcher')
            ->assertSee('Faculty Researcher')
            ->assertDontSee('All Users');
        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'applicant-type-filter'),
            strpos($content, 'institution-filter'),
        );

        $this->actingAs($adviser)
            ->get(route('adviser.applicants.index', [
                'applicant_type' => ApplicantType::Student->value,
            ]))
            ->assertOk()
            ->assertSee($student->name)
            ->assertDontSee($faculty->name);
    }

    public function test_res_lead_can_create_an_adviser_without_position_or_designation(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $response = $this->actingAs($resLead)->post(route('res.users.store'), $this->reviewerPayload([
            'first_name' => 'Optional',
            'last_name' => 'Position',
            'email' => 'optional.position@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-OPTIONAL',
            'role' => UserRole::Adviser->value,
            'position_title' => null,
            'reviewer_classification' => null,
            'reviewer_capacity' => null,
        ]));

        $created = User::where('email', 'optional.position@ecrats.test')->firstOrFail();
        $response->assertRedirect(route('res.users.show', ['managedUser' => $created, 'created' => 1]));
        $this->assertNull($created->position_title);

        $adviserType = collect(app(AccountTypeCatalog::class)->allowedFor($resLead))->firstWhere('key', 'adviser');
        $this->assertNotContains('Position / Designation', $adviserType['required_headers']);
        $this->assertContains('Position / Designation', $adviserType['optional_headers']);
    }

    public function test_adviser_creation_exposes_and_enforces_reviewer_capability_capacity_conditionally(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->get(route('res.users.create', ['mode' => 'individual', 'account_type' => 'adviser']))
            ->assertOk()
            ->assertSee('Reviewer Capability')
            ->assertSee('name="reviewer_enabled"', false)
            ->assertSee('data-reviewer-capability-toggle', false)
            ->assertSee('Reviewer Capacity')
            ->assertSee('data-reviewer-capacity', false)
            ->assertDontSee('Reviewer Classification');

        $payload = $this->reviewerPayload([
            'email' => 'conditional.reviewer@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-CONDITIONAL',
            'role' => UserRole::Adviser->value,
            'reviewer_enabled' => '1',
            'reviewer_capacity' => null,
            'position_title' => null,
        ]);
        $this->actingAs($resLead)
            ->post(route('res.users.store'), $payload)
            ->assertSessionHasErrors('reviewer_capacity');
        $this->assertDatabaseMissing('users', ['email' => 'conditional.reviewer@ecrats.test']);

        $this->actingAs($resLead)
            ->post(route('res.users.store'), [...$payload, 'reviewer_capacity' => 8])
            ->assertSessionDoesntHaveErrors();
        $created = User::query()->where('email', 'conditional.reviewer@ecrats.test')->firstOrFail();
        $this->assertTrue($created->reviewer_enabled);
        $this->assertSame(8, $created->reviewer_capacity);
        $this->assertNull($created->position_title);
    }

    public function test_res_lead_can_deactivate_and_reactivate_an_individual_account(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $subject = User::factory()->create([
            'username' => 'reactivate.user',
            'password' => 'reactivate-password',
        ]);

        $this->actingAs($resLead)->patch(route('res.users.status', $subject), [
            'account_status' => 'inactive',
        ])->assertRedirect();
        $this->assertSame('inactive', $subject->refresh()->account_status);

        $this->actingAs($resLead)->get(route('res.users.show', $subject))
            ->assertOk()
            ->assertSee('Reactivate')
            ->assertSee('identity-button-reactivate', false)
            ->assertSee('Delete');

        $this->actingAs($resLead)->patch(route('res.users.status', $subject), [
            'account_status' => 'active',
        ])->assertRedirect();
        $this->assertSame('active', $subject->refresh()->account_status);

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'username' => $subject->username,
            'password' => 'reactivate-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($subject);
    }

    public function test_res_lead_individual_delete_soft_deletes_the_account(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $subject = User::factory()->create();

        $this->actingAs($resLead)
            ->delete(route('res.users.destroy', $subject))
            ->assertRedirect(route('res.users.index'));

        $this->assertSoftDeleted('users', ['id' => $subject->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.archived',
            'subject_id' => $subject->id,
        ]);
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

    public function test_res_lead_can_send_an_active_account_a_single_use_password_reset_link(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $activeUser = User::factory()->create([
            'email' => 'active.reset@ecrats.test',
            'password' => 'old-password',
        ]);
        $resetUrl = null;

        $this->actingAs($resLead)
            ->post(route('res.users.password-reset', $activeUser))
            ->assertRedirect();

        Notification::assertSentTo(
            $activeUser,
            AccountSetupNotification::class,
            function (AccountSetupNotification $notification) use ($activeUser, &$resetUrl): bool {
                $mail = $notification->toMail($activeUser);
                $resetUrl = (string) $mail->actionUrl;

                return $mail->subject === 'Reset your ECRATS password'
                    && str_contains($resetUrl, '/reset-password/');
            },
        );

        $this->assertIsString($resetUrl);
        $path = parse_url($resetUrl, PHP_URL_PATH);
        $token = basename((string) $path);
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $activeUser->email,
            'password' => 'new-active-password',
            'password_confirmation' => 'new-active-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-active-password', $activeUser->refresh()->password));
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $activeUser->email,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_adviser_can_send_an_active_managed_applicant_a_reset_link_but_not_an_unrelated_applicant(): void
    {
        Notification::fake();
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $managedApplicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'created_by_user_id' => $adviser,
        ]);
        $unrelatedApplicant = User::factory()->create(['role' => UserRole::Applicant]);

        $this->actingAs($adviser)
            ->post(route('adviser.applicants.password-reset', $managedApplicant))
            ->assertRedirect();
        Notification::assertSentTo($managedApplicant, AccountSetupNotification::class);

        $this->actingAs($adviser)
            ->post(route('adviser.applicants.password-reset', $unrelatedApplicant))
            ->assertForbidden();
        Notification::assertNotSentTo($unrelatedApplicant, AccountSetupNotification::class);
    }

    public function test_excel_template_has_exact_structure_role_headers_and_database_options(): void
    {
        $this->requireSpreadsheetRuntime();

        // Arrange an authorized actor and one database-backed option added after the default migration data.
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        app(ProfileOptionCatalog::class)->create($resLead, ProfileOptionField::Institute, 'Institute of Applied Computing Studies', 'IACS');

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
        $this->assertStringContainsString('EcratsInstituteOptions', $workbook);
        $this->assertStringNotContainsString('EcratsDepartmentOptions', $workbook);
        $zip->close();

        // Act through the trusted reader so content checks remain valid whether strings are inline or shared.
        $reader = new XlsxReader;
        $spreadsheet = $reader->load($path);
        $accounts = $spreadsheet->getSheetByName('Accounts');
        $optionsText = collect($spreadsheet->getSheetByName('Options')?->toArray() ?? [])->flatten()->implode(' ');
        $instructionsText = collect($spreadsheet->getSheetByName('Instructions')?->toArray() ?? [])->flatten()->implode(' ');

        // Assert role headers and the complete approved realistic Student example after reader decoding.
        $this->assertSame(['First Name', 'Middle Name', 'Last Name'], [
            (string) $accounts?->getCell('A1')->getValue(),
            (string) $accounts?->getCell('B1')->getValue(),
            (string) $accounts?->getCell('C1')->getValue(),
        ]);
        $this->assertSame('Institute', (string) $accounts?->getCell('I1')->getValue());
        $this->assertSame([
            'Juan',
            'Dela',
            'Cruz',
            'Jr.',
            'juandelacruz@example.com',
            '20260000',
            '09999999999',
            '4th Year',
            'Institute of Computing and Digital Innovation',
            'Bachelor of Science in Computer Science',
        ], array_map(
            fn (string $column): string => (string) $accounts?->getCell($column.'2')->getValue(),
            range('A', 'J'),
        ));
        $this->assertContains($accounts?->getCell('F2')->getDataType(), [DataType::TYPE_STRING, DataType::TYPE_INLINE]);
        $this->assertContains($accounts?->getCell('G2')->getDataType(), [DataType::TYPE_STRING, DataType::TYPE_INLINE]);

        // Assert dropdowns, database options, and the visible marker remain in their dedicated worksheets.
        $this->assertNotEmpty($accounts?->getDataValidationCollection());
        $this->assertTrue($accounts?->getStyle('A1')->getAlignment()->getWrapText());
        $this->assertStringContainsString('Institute of Applied Computing Studies', $optionsText);
        $this->assertStringContainsString('Institute of Engineering', $optionsText);
        $this->assertStringNotContainsString('Department', $optionsText);
        $this->assertStringContainsString(AccountTypeCatalog::EXAMPLE_MARKER, $instructionsText);
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

    public function test_bulk_import_revalidation_preserves_the_selected_account_type(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $validationUrl = route('res.users.import.store', [
            'account_type' => 'student_researcher',
        ]);

        $this->actingAs($resLead)
            ->get(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->assertOk()
            ->assertSee('Excel Bulk Import: Student Researcher')
            ->assertSee('action="'.$validationUrl.'"', false);

        $this->actingAs($resLead)
            ->from($validationUrl)
            ->post($validationUrl, ['account_type' => 'student_researcher'])
            ->assertRedirect($validationUrl)
            ->assertSessionHasErrors('accounts_file');

        $this->actingAs($resLead)
            ->get($validationUrl)
            ->assertOk()
            ->assertSee('Excel Bulk Import: Student Researcher')
            ->assertDontSee('Select Account Type');
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

    public function test_adviser_bulk_import_matches_individual_reviewer_capability_validation(): void
    {
        Notification::fake();
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $invalidPath = $this->templatePath($resLead, 'adviser');
        $invalidRow = $this->adviserRow(
            email: 'bulk.invalid.reviewer@ecrats.test',
            identifier: 'KLD-EMP-BULK-INVALID',
            reviewerEnabled: 'Yes',
            reviewerCapacity: '',
        );
        $this->replaceSpreadsheetRow($invalidPath, 3, $invalidRow);
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'adviser',
            'accounts_file' => $this->uploadedWorkbook($invalidPath, 'invalid-adviser-capacity.xlsx'),
        ])->assertOk()
            ->assertSee('Excel Row 3')
            ->assertSee('Reviewer Capacity')
            ->assertSee('required when Reviewer capability is enabled');

        $validPath = $this->templatePath($resLead, 'adviser');
        $this->replaceSpreadsheetRow($validPath, 3, $this->adviserRow(
            email: 'bulk.valid.reviewer@ecrats.test',
            identifier: 'KLD-EMP-BULK-VALID',
            reviewerEnabled: 'Yes',
            reviewerCapacity: '9',
        ));
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'adviser',
            'accounts_file' => $this->uploadedWorkbook($validPath, 'valid-adviser-capacity.xlsx'),
        ])->assertOk()
            ->assertSee('Import Preview')
            ->assertSee('bulk.valid.reviewer@ecrats.test');

        $token = $this->previewTokenFor($resLead);
        $this->actingAs($resLead)
            ->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertRedirect(route('res.users.index'));

        $created = User::query()->where('email', 'bulk.valid.reviewer@ecrats.test')->firstOrFail();
        $this->assertSame(UserRole::Adviser, $created->role);
        $this->assertTrue($created->reviewer_enabled);
        $this->assertSame(9, $created->reviewer_capacity);
    }

    public function test_phone_numbers_require_exactly_eleven_digits_and_bulk_import_accepts_alphanumeric_student_ids(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->from(route('res.users.create', ['mode' => 'individual', 'account_type' => 'student_researcher']))
            ->post(route('res.users.store'), $this->studentPayload([
                'email' => 'invalid.phone@ecrats.test',
                'institutional_identifier' => 'STU-PHONE-12',
                'phone_number' => '091712345678',
            ]))
            ->assertSessionHasErrors('phone_number');
        $this->assertDatabaseMissing('users', ['email' => 'invalid.phone@ecrats.test']);

        $invalidPath = $this->templatePath($resLead);
        $invalidRow = $this->studentRow(
            email: 'bulk.invalid.phone@ecrats.test',
            identifier: 'STU-BULK-A1',
        );
        $invalidRow[6] = '091712345678';
        $this->replaceSpreadsheetRow($invalidPath, 3, $invalidRow);

        $this->actingAs($resLead)
            ->post(route('res.users.import.store'), [
                'account_type' => 'student_researcher',
                'accounts_file' => $this->uploadedWorkbook($invalidPath, 'invalid-phone.xlsx'),
            ])
            ->assertOk()
            ->assertSee('Excel Row 3')
            ->assertSee('Phone Number')
            ->assertSee('exactly 11 digits');

        $validPath = $this->templatePath($resLead);
        $validRow = $this->studentRow(
            email: 'bulk.alphanumeric@ecrats.test',
            identifier: 'STU-2026-A1',
        );
        $validRow[6] = '09171234567';
        $this->replaceSpreadsheetRow($validPath, 3, $validRow);

        $this->actingAs($resLead)
            ->post(route('res.users.import.store'), [
                'account_type' => 'student_researcher',
                'accounts_file' => $this->uploadedWorkbook($validPath, 'alphanumeric-student-id.xlsx'),
            ])
            ->assertOk()
            ->assertSee('Import Preview')
            ->assertSee('STU-2026-A1')
            ->assertDontSee('Excel Row 3</strong>', false);
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

    public function test_legacy_download_example_is_skipped_while_unmarked_row_two_is_normal_input(): void
    {
        // Arrange a legacy downloaded template with one genuine account on Row 3.
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $examplePath = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($examplePath, 3, $this->studentRow(
            firstName: 'Real',
            lastName: 'Student',
            email: 'real.student@ecrats.test',
            identifier: 'KLD-STU-901',
        ));

        // Act and assert that the unchanged legacy Row 2 example is skipped.
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($examplePath),
        ])->assertOk()->assertSee('real.student@ecrats.test')->assertDontSee('juandelacruz@example.com');

        // Arrange a different valid Row 2 and remove the legacy Instructions marker.
        $rowTwoPath = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($rowTwoPath, 2, $this->studentRow(
            firstName: 'Row Two',
            lastName: 'Account',
            email: 'row.two@ecrats.test',
            identifier: 'KLD-STU-902',
        ));
        $this->removeExampleMarker($rowTwoPath);

        // Act and assert that the unmarked physical Row 2 is validated as ordinary account data.
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($rowTwoPath, 'row-two.xlsx'),
        ])->assertOk()->assertSee('row.two@ecrats.test')->assertSee('Excel Row');
    }

    public function test_res_lead_preview_separates_active_and_archived_accounts_and_restores_original_record(): void
    {
        // Arrange one active match, one archived match, and a relationship owned by the archived user.
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $active = User::factory()->create([
            'email' => 'active.match@ecrats.test',
            'institutional_identifier' => 'KLD-STU-ACTIVE',
        ]);
        $archived = User::factory()->create([
            'email' => 'archived.match@ecrats.test',
            'institutional_identifier' => 'KLD-STU-ARCHIVED',
        ]);
        $relatedApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $archived,
        ]);
        $archivedId = $archived->id;
        $archived->delete();
        $path = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($path, 3, $this->studentRow(
            email: $active->email,
            identifier: $active->institutional_identifier,
        ));
        $this->replaceSpreadsheetRow($path, 4, $this->studentRow(
            firstName: 'Archived',
            lastName: 'Match',
            email: $archived->email,
            identifier: $archived->institutional_identifier,
        ));

        // Act by validating the workbook and inspecting the two separate result containers.
        $previewResponse = $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ]);

        // Assert only the archived container exposes restoration controls.
        $previewResponse->assertOk()
            ->assertSee('Active Existing Accounts (1)')
            ->assertSee('Archived Accounts Found (1)')
            ->assertSee('Restore All Flagged Archived Accounts');
        $content = (string) $previewResponse->getContent();
        $activeStart = strpos($content, 'Active Existing Accounts');
        $archivedStart = strpos($content, 'Archived Accounts Found');
        $this->assertIsInt($activeStart);
        $this->assertIsInt($archivedStart);
        $this->assertStringNotContainsString(
            'data-restore-confirm',
            substr($content, $activeStart, $archivedStart - $activeStart),
        );

        // Act through the individual restore endpoint using the actor-owned opaque preview.
        $token = $this->previewTokenFor($resLead);
        $this->actingAs($resLead)->post(route('res.users.import.restore-account'), [
            'import_token' => $token,
            'archived_user_id' => $archivedId,
        ])->assertRedirect(route('res.users.import.form', ['account_type' => 'student_researcher']));

        // Assert soft deletion was reversed in place and all foreign-key history still resolves to the same ID.
        $this->assertSame($archivedId, User::withTrashed()->findOrFail($archivedId)->id);
        $this->assertNull(User::withTrashed()->findOrFail($archivedId)->deleted_at);
        $this->assertSame($archivedId, $relatedApplication->fresh()->applicant_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $resLead->id,
            'action' => 'user.archived_account_restored',
            'subject_id' => $archivedId,
        ]);

        // Act on confirmation and assert the restored row is never inserted as a new account.
        $this->actingAs($resLead)->post(route('res.users.import.confirm'), [
            'import_token' => $token,
        ])->assertRedirect(route('res.users.index'));
        $this->assertSame(1, User::withTrashed()->where('email', $archived->email)->count());
        $this->assertSame(1, User::withTrashed()->where('email', $active->email)->count());
    }

    public function test_restore_all_uses_only_archived_accounts_in_the_current_preview(): void
    {
        // Arrange two preview-listed archived accounts and one archived account absent from the workbook.
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $first = User::factory()->create([
            'email' => 'first.archived@ecrats.test',
            'institutional_identifier' => 'KLD-STU-RESTORE-1',
        ]);
        $second = User::factory()->create([
            'email' => 'second.archived@ecrats.test',
            'institutional_identifier' => 'KLD-STU-RESTORE-2',
        ]);
        $unlisted = User::factory()->create([
            'email' => 'unlisted.archived@ecrats.test',
            'institutional_identifier' => 'KLD-STU-UNLISTED',
        ]);
        $first->delete();
        $second->delete();
        $unlisted->delete();
        $path = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($path, 3, $this->studentRow(
            email: $first->email,
            identifier: $first->institutional_identifier,
        ));
        $this->replaceSpreadsheetRow($path, 4, $this->studentRow(
            email: $second->email,
            identifier: $second->institutional_identifier,
        ));
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ])->assertOk();
        $token = $this->previewTokenFor($resLead);

        // Act once through the bulk restoration endpoint.
        $this->actingAs($resLead)->post(route('res.users.import.restore-all'), [
            'import_token' => $token,
        ])->assertRedirect();

        // Assert only the two server-previewed rows were restored and every event was audited.
        $this->assertNull($first->fresh()->deleted_at);
        $this->assertNull($second->fresh()->deleted_at);
        $this->assertNotNull(User::withTrashed()->findOrFail($unlisted->id)->deleted_at);
        $this->assertDatabaseCount('audit_logs', 5);
        $this->assertSame(2, AuditLog::where('action', 'user.archived_account_restored')->count());
        $this->assertSame(1, AuditLog::where('action', 'user.bulk_archived_accounts_restored')->count());
    }

    public function test_restoration_rechecks_the_archived_accounts_exact_template_identity(): void
    {
        // Arrange a Student preview whose archived target is changed to a Faculty identity after validation.
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $archived = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
            'email' => 'type.changed@ecrats.test',
            'institutional_identifier' => 'KLD-STU-TYPE-CHECK',
        ]);
        $archived->delete();
        $path = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($path, 3, $this->studentRow(
            email: $archived->email,
            identifier: $archived->institutional_identifier,
        ));
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ])->assertOk();
        $token = $this->previewTokenFor($resLead);

        // Act after changing the authoritative archived role metadata behind the preview.
        User::withTrashed()->whereKey($archived->id)->update([
            'applicant_type' => ApplicantType::Faculty->value,
        ]);
        $this->actingAs($resLead)->post(route('res.users.import.restore-account'), [
            'import_token' => $token,
            'archived_user_id' => $archived->id,
        ])->assertRedirect();

        // Assert the account remains archived and the blocked identity mismatch is auditable.
        $this->assertNotNull(User::withTrashed()->findOrFail($archived->id)->deleted_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $resLead->id,
            'action' => 'user.archived_account_restore_blocked',
            'subject_id' => $archived->id,
        ]);
    }

    public function test_restoration_rejects_other_actors_expired_previews_and_unlisted_ids(): void
    {
        // Arrange one owner-bound preview and a second archived account not represented by that preview.
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::ResLead]);
        $otherActor = User::factory()->create(['role' => UserRole::ResLead]);
        $listed = User::factory()->create([
            'email' => 'listed.archived@ecrats.test',
            'institutional_identifier' => 'KLD-STU-LISTED',
        ]);
        $unlisted = User::factory()->create([
            'email' => 'other.archived@ecrats.test',
            'institutional_identifier' => 'KLD-STU-OTHER',
        ]);
        $listed->delete();
        $unlisted->delete();
        $path = $this->templatePath($owner);
        $this->replaceSpreadsheetRow($path, 3, $this->studentRow(
            email: $listed->email,
            identifier: $listed->institutional_identifier,
        ));
        $this->actingAs($owner)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ])->assertOk();
        $token = $this->previewTokenFor($owner);

        // Act and assert that actor-directory isolation blocks a different RES Lead.
        $this->actingAs($otherActor)->post(route('res.users.import.restore-all'), [
            'import_token' => $token,
        ])->assertSessionHasErrors('import_token');

        // Act and assert that a browser-manipulated user ID cannot escape the server-stored archived category.
        $this->actingAs($owner)->post(route('res.users.import.restore-account'), [
            'import_token' => $token,
            'archived_user_id' => $unlisted->id,
        ])->assertSessionHasErrors('archived_user_id');
        $this->assertNotNull(User::withTrashed()->findOrFail($unlisted->id)->deleted_at);

        // Act after the fixed lifetime and assert even the rightful owner can no longer restore the listed row.
        $this->travel(31)->minutes();
        $this->actingAs($owner)->post(route('res.users.import.restore-all'), [
            'import_token' => $token,
        ])->assertSessionHasErrors('import_token');
        $this->assertNotNull(User::withTrashed()->findOrFail($listed->id)->deleted_at);
    }

    public function test_adviser_sees_archived_guidance_without_restoration_controls_or_route_access(): void
    {
        // Arrange an Adviser-authorized Student import containing one archived match.
        Storage::fake('local');
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $archived = User::factory()->create([
            'email' => 'adviser.archived@ecrats.test',
            'institutional_identifier' => 'KLD-STU-ADVISER',
        ]);
        $archived->delete();
        $path = $this->templatePath($adviser);
        $this->replaceSpreadsheetRow($path, 3, $this->studentRow(
            email: $archived->email,
            identifier: $archived->institutional_identifier,
        ));

        // Act through the Adviser preview and assert guidance replaces every restore command.
        $response = $this->actingAs($adviser)->post(route('adviser.applicants.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ]);
        $response->assertOk()
            ->assertSee('Archived Accounts Found (1)')
            ->assertSee('Contact the RES Lead')
            ->assertDontSee('data-restore-confirm', false)
            ->assertDontSee('Restore All Flagged Archived Accounts');

        // Act directly against the RES endpoint and assert role middleware denies the Adviser.
        $token = $this->previewTokenFor($adviser);
        $this->actingAs($adviser)->post(route('res.users.import.restore-all'), [
            'import_token' => $token,
        ])->assertRedirect(route('dashboard'));
        $this->assertNotNull(User::withTrashed()->findOrFail($archived->id)->deleted_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $adviser->id,
            'action' => 'auth.authorization_denied',
        ]);
    }

    public function test_excel_validation_rejects_invalid_rows_and_formulas_but_accepts_flexible_sheets_and_external_link_metadata(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $invalidPath = $this->templatePath($resLead);
        $invalidRow = $this->studentRow(email: 'not-an-email', identifier: 'KLD-STU-701');
        $invalidRow[7] = 'Unknown Year Level';
        $this->replaceSpreadsheetRow($invalidPath, 3, $invalidRow);
        $invalidResponse = $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
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
        $this->replaceSpreadsheetRow($renamedSheetPath, 3, $this->studentRow(
            email: 'renamed.sheet@ecrats.test',
            identifier: 'KLD-STU-RENAMED',
        ));
        $this->replaceZipEntry($renamedSheetPath, 'xl/workbook.xml', fn (string $xml): string => str_replace('name="Instructions"', 'name="Unexpected"', $xml));
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($renamedSheetPath, 'renamed-sheet.xlsx'),
        ])->assertOk()->assertSee('renamed.sheet@ecrats.test');

        $externalPath = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($externalPath, 3, $this->studentRow(
            email: 'external.metadata@ecrats.test',
            identifier: 'KLD-STU-EXTERNAL',
        ));
        $this->addZipEntry($externalPath, 'xl/externalLinks/externalLink1.xml', '<externalLink xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
        $this->addZipEntry($externalPath, 'xl/externalLinks/_rels/externalLink1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/externalLinkPath" Target="https://example.invalid/source.xlsx" TargetMode="External"/></Relationships>');
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($externalPath, 'external-link.xlsx'),
        ])->assertOk()->assertSee('external.metadata@ecrats.test');

        $ddePath = $this->templatePath($resLead);
        $this->addZipEntry($ddePath, 'xl/externalLinks/externalLink1.xml', '<externalLink xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><ddeLink ddeService="cmd" ddeTopic="/c calc"/></externalLink>');
        $this->assertWorkbookRejected($resLead, $ddePath, 'dde-link.xlsx');
    }

    public function test_import_discovers_reordered_required_headers_on_a_renamed_hidden_worksheet(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $path = Storage::disk('local')->path('flexible-import.xlsx');
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('Read Me')->setCellValue('A1', 'Prepared outside ECRATS');
        $accounts = $workbook->createSheet();
        $accounts->setTitle('People 2026')->setSheetState('hidden');
        $headers = ['EMAIL', 'Phone_Number', 'Student Number', 'FIRST-NAME', 'Year Level', 'Last Name', 'Internal Note'];
        $values = ['flexible.student@ecrats.test', '09171234567', 'KLD-STU-FLEX', 'Flexible', '4th Year', 'Student', 'Ignored safely'];

        foreach ($headers as $index => $header) {
            $accounts->setCellValue([$index + 1, 4], $header);
            $accounts->setCellValue([$index + 1, 5], $values[$index]);
        }

        (new XlsxWriter($workbook))->save($path);
        $workbook->disconnectWorksheets();

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path, 'independent-workbook.xlsx'),
        ])->assertOk()
            ->assertSee('Import Preview')
            ->assertSee('flexible.student@ecrats.test')
            ->assertSee('KLD-STU-FLEX');
    }

    public function test_import_page_describes_compatible_workbooks_and_inert_external_links(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->get(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->assertOk()
            ->assertSee('Optional Template')
            ->assertSee('Required headers may be reordered')
            ->assertSee('never opens, fetches, resolves, or trusts external workbook targets');
    }

    public function test_optional_declared_account_type_must_match_the_selected_import(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $facultyWorkbook = $this->templatePath($resLead, 'faculty_researcher');

        $this->actingAs($resLead)
            ->from(route('res.users.import.form', ['account_type' => 'adviser']))
            ->post(route('res.users.import.store'), [
                'account_type' => 'adviser',
                'accounts_file' => $this->uploadedWorkbook($facultyWorkbook, 'faculty-as-adviser.xlsx'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('accounts_file');
    }

    /**
     * Verify a later successful validation clears the general error, red badge, and stale modal details.
     */
    public function test_successful_revalidation_clears_the_import_error_state(): void
    {
        // Arrange one invalid student workbook and one corrected workbook for the same authorized user.
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $invalidPath = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($invalidPath, 3, $this->studentRow(
            email: 'invalid-email',
            identifier: 'KLD-STU-REVALIDATE',
        ));

        // Act with invalid data and assert the response exposes the unresolved indicator and generic message.
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($invalidPath, 'invalid-revalidation.xlsx'),
        ])->assertOk()
            ->assertSee('An error occurred.')
            ->assertSee('has-errors is-attention', false);

        // Arrange corrected values in a fresh current template to model the user's successful revalidation.
        $validPath = $this->templatePath($resLead);
        $this->replaceSpreadsheetRow($validPath, 3, $this->studentRow(
            email: 'corrected.student@ecrats.test',
            identifier: 'KLD-STU-REVALIDATE',
        ));

        // Act again and assert only the clean modal state remains in the new server-authoritative response.
        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
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

    public function test_excel_upload_enforces_file_required_header_unlabeled_column_and_row_limits(): void
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

        $headerPath = $this->templatePath($resLead);
        $this->replaceZipEntry($headerPath, 'xl/worksheets/sheet1.xml', fn (string $xml): string => str_replace('Phone Number', 'Telephone', $xml));
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
        $overflowWorkbook = (new XlsxReader)->load($extraRowPath);
        $accounts = $overflowWorkbook->getSheetByName('Accounts');

        for ($row = 3; $row <= SafeSpreadsheet::MAX_ACCOUNT_ROWS + 3; $row++) {
            $values = $this->studentRow(
                email: 'student'.$row.'@ecrats.test',
                identifier: 'KLD-STU-'.$row,
            );

            foreach ($values as $column => $value) {
                $accounts?->setCellValue([$column + 1, $row], $value);
            }
        }

        (new XlsxWriter($overflowWorkbook))->save($extraRowPath);
        $overflowWorkbook->disconnectWorksheets();
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

    public function test_old_workbook_label_resolves_to_the_same_active_option_after_rename(): void
    {
        Notification::fake();
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $catalog = app(ProfileOptionCatalog::class);
        $option = $catalog->create($resLead, ProfileOptionField::Program, 'Applied Ethics');
        $path = $this->templatePath($resLead);
        $row = $this->studentRow(
            'Alias',
            'Import',
            'alias.import@ecrats.test',
            'KLD-STU-ALIAS',
        );
        $row[9] = 'Applied Ethics';
        $this->replaceSpreadsheetRow($path, 3, $row);

        $catalog->update($resLead, $option, 'Research Ethics');
        $this->assertDatabaseHas('profile_option_aliases', [
            'profile_option_id' => $option->id,
            'field' => ProfileOptionField::Program->value,
            'normalized_value' => 'applied ethics',
        ]);

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path, 'older-template.xlsx'),
        ])->assertOk()
            ->assertSee('Confirm Account Creation')
            ->assertDontSee('Select an accepted Program');

        $token = $this->previewTokenFor($resLead);
        $this->actingAs($resLead)
            ->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertRedirect(route('res.users.index'));

        $this->assertDatabaseHas('users', [
            'institutional_identifier' => 'KLD-STU-ALIAS',
            'program' => 'Research Ethics',
        ]);
        $this->assertSame(
            $option->id,
            $catalog->resolve(ProfileOptionField::Program, 'Applied Ethics')?->id,
        );

        $catalog->setActive($resLead, $option->refresh(), false);
        $inactiveRow = $this->studentRow(
            'Inactive',
            'Alias',
            'inactive.alias@ecrats.test',
            'KLD-STU-INACTIVE-ALIAS',
        );
        $inactiveRow[9] = 'Applied Ethics';
        $this->replaceSpreadsheetRow($path, 3, $inactiveRow);

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path, 'older-template-inactive.xlsx'),
        ])->assertOk()
            ->assertSee('Select an accepted Program')
            ->assertDontSee('Confirm Account Creation');
        $this->assertDatabaseMissing('users', ['institutional_identifier' => 'KLD-STU-INACTIVE-ALIAS']);
    }

    public function test_required_option_configuration_is_reported_in_templates_and_import_validation(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        ProfileOption::where('field', ProfileOptionField::YearLevel->value)->update(['is_active' => false]);
        $path = $this->templatePath($resLead);

        $this->assertStringContainsString(
            'No active Year Level options are configured.',
            $this->workbookEntry($path, 'xl/worksheets/sheet3.xml'),
        );
        $this->replaceSpreadsheetRow($path, 3, $this->studentRow(
            email: 'unconfigured.student@ecrats.test',
            identifier: 'KLD-STU-UNCONFIGURED',
        ));

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $this->uploadedWorkbook($path),
        ])->assertOk()
            ->assertSee('No accepted Year Level options are configured')
            ->assertDontSee('Confirm Account Creation');

        $this->assertDatabaseMissing('users', ['institutional_identifier' => 'KLD-STU-UNCONFIGURED']);
    }

    public function test_audit_log_filters_hide_completion_events_and_sensitive_metadata(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $reviewer = User::factory()->reviewer()->create(['name' => 'Filtered Reviewer']);
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
        $this->actingAs($resLead)->get(route('res.reports.audit.index', [
            'search' => 'Filtered',
            'role' => UserRole::Adviser->value,
            'result' => 'completed',
            'target_type' => User::class,
            'date_from' => $today,
            'date_to' => $today,
        ]))
            ->assertOk()
            ->assertSee('Filtered Reviewer')
            ->assertSee('Adviser')
            ->assertSee('Profile Updated')
            ->assertDontSee('Onboarding Completed')
            ->assertDontSee('Password Setup Completed')
            ->assertDontSee('>Subject<', false)
            ->assertSee('result=completed', false);
        $this->assertNotNull($visible->id);
    }

    public function test_audit_log_filters_by_semester_and_academic_year(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $firstTerm = AcademicTerm::create([
            'semester' => '1st Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);
        $secondTerm = AcademicTerm::create([
            'semester' => '2nd Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->addMonths(2),
            'ends_at' => now()->addMonths(5),
            'is_active' => true,
        ]);
        AuditLog::create([
            'academic_term_id' => $firstTerm->id,
            'actor_user_id' => $resLead->id,
            'action' => 'term.first.activity',
            'metadata' => ['result' => 'recorded'],
        ]);
        AuditLog::create([
            'academic_term_id' => $secondTerm->id,
            'actor_user_id' => $resLead->id,
            'action' => 'term.second.activity',
            'metadata' => ['result' => 'recorded'],
        ]);

        $this->actingAs($resLead)
            ->get(route('res.reports.audit.index', [
                'semester' => '1st Semester',
                'academic_year' => '2026-2027',
            ]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1
                && $logs->first()->action === 'term.first.activity');
    }

    public function test_application_audit_events_retain_the_parent_application_term(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $historicalTerm = AcademicTerm::create([
            'semester' => 'Historical Semester',
            'academic_year' => '2025-2026',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->subMonths(2),
            'is_active' => true,
        ]);
        AcademicTerm::create([
            'semester' => 'Current Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(4),
            'is_active' => true,
        ]);
        $application = ResearchApplication::factory()->create([
            'academic_term_id' => $historicalTerm,
        ]);

        $audit = app(AuditLogService::class)->record(
            $resLead,
            'application.historical_action',
            $application,
            ['result' => 'recorded'],
        );

        $this->assertSame($historicalTerm->id, $audit->academic_term_id);
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
            ->assertDontSee('Dropdown Options')
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
            ->assertDontSee('Dropdown Options')
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
            ->assertSee('Send Reset Link')
            ->assertSee('identity-account-lifecycle-actions', false);

        $css = (string) file_get_contents(resource_path('css/dashboard.css'));
        $this->assertMatchesRegularExpression(
            '/\.identity-account-lifecycle-actions\s*>\s*form\s*\{[^}]*align-items:\s*stretch;[^}]*margin:\s*0;[^}]*padding:\s*0;/s',
            $css,
        );

        $this->actingAs($resLead)
            ->get(route('res.users.edit', $managedUser))
            ->assertOk()
            ->assertSee('identity-edit-page', false)
            ->assertDontSee('Dropdown Options');

        $this->actingAs($resLead)
            ->get(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->assertOk()
            ->assertSee('identity-template-heading', false)
            ->assertSee('identity-template-actions', false);

        // Assert RES Lead and Adviser account lists contain keyboard-focusable internal horizontal-scroll regions.
        $this->actingAs($resLead)
            ->get(route('res.users.index'))
            ->assertOk()
            ->assertSee('class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="User account results" tabindex="0"', false);
        $this->actingAs($adviser)
            ->get(route('adviser.applicants.index'))
            ->assertOk()
            ->assertSee('class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="User account results" tabindex="0"', false);

        // Assert the audit table uses the same wrapper and individual forms expose one reusable section-title class.
        $this->actingAs($resLead)
            ->get(route('res.reports.audit.index'))
            ->assertOk()
            ->assertSee('class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Account audit results" tabindex="0"', false);
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
        $this->assertSame('kld.stu.903.oldname', $subject->refresh()->username);
        Notification::assertNotSentTo($subject, UsernameChangedNotification::class);

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
            'phone_number' => '09171234567',
            'institution' => 'Institute of Engineering',
            'program' => null,
            'year_level' => '4th Year',
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
            'phone_number' => '09171234567',
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
            '09171234567',
            '4th Year',
            'Institute of Engineering',
            '',
        ];
    }

    /** @return array<int, string> */
    private function adviserRow(
        string $email,
        string $identifier,
        string $reviewerEnabled,
        string $reviewerCapacity,
    ): array {
        return [
            'Bulk',
            '',
            'Adviser',
            '',
            $email,
            $identifier,
            '09171234567',
            'Institute of Engineering',
            '',
            $reviewerEnabled,
            $reviewerCapacity,
        ];
    }

    private function templatePath(User $actor, string $accountType = 'student_researcher'): string
    {
        $this->requireSpreadsheetRuntime();

        return app(SafeSpreadsheet::class)->createTemplate(
            app(AccountTypeCatalog::class)->authorized($actor, $accountType),
            app(ProfileOptionCatalog::class)->grouped(),
        );
    }

    private function requireSpreadsheetRuntime(): void
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(Spreadsheet::class)) {
            $this->markTestSkipped('The ZIP extension and installed PhpSpreadsheet package are required for XLSX round-trip coverage.');
        }
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
        // Replace one complete Accounts row while preserving all unrelated verified workbook parts.
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

    private function removeExampleMarker(string $path): void
    {
        // Remove the visible marker through the supported reader/writer so Row 2 becomes ordinary input.
        $spreadsheet = (new XlsxReader)->load($path);
        $instructions = $spreadsheet->getSheetByName('Instructions');

        foreach ($instructions?->getRowIterator() ?? [] as $row) {
            $rowNumber = $row->getRowIndex();

            if ((string) $instructions?->getCell('A'.$rowNumber)->getValue() === 'Example Row Marker') {
                $instructions?->setCellValue('B'.$rowNumber, '');

                break;
            }
        }

        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function replaceZipEntry(string $path, string $entry, callable $replace): void
    {
        $zip = $this->openWorkbook($path);
        $contents = $zip->getFromName($entry);
        $this->assertIsString($contents);
        $this->assertTrue($zip->addFromString($entry, $replace($contents)));
        $zip->close();
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
