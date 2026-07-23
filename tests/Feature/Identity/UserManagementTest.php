<?php

namespace Tests\Feature\Identity;

use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\ReviewerClassification;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountSetupNotification;
use App\Notifications\UsernameChangedNotification;
use App\Services\Identity\SafeSpreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
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

        $this->assertTrue($pending->password_setup_completed_at === null);
    }

    public function test_individual_creation_generates_pending_account_and_sends_username_setup_link(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $response = $this->actingAs($resLead)->post(route('res.users.store'), $this->studentPayload([
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'JUAN.DELA.CRUZ@ECRATS.TEST',
            'institutional_identifier' => 'kld-stu-501',
            'username' => 'manual-name',
            'password' => 'creator-password-must-be-ignored',
        ]));

        $user = User::where('email', 'juan.dela.cruz@ecrats.test')->firstOrFail();
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

    public function test_pending_setup_account_cannot_login_and_setup_token_is_single_use(): void
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

        $user->refresh();
        $this->assertSame('active', $user->account_status);
        $this->assertNotNull($user->password_setup_completed_at);
        $this->assertTrue(Hash::check('newsecurepass', $user->password));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'anothersecurepass',
            'password_confirmation' => 'anothersecurepass',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('newsecurepass', $user->refresh()->password));
    }

    public function test_setup_token_expires_after_one_week(): void
    {
        $user = User::factory()->pendingSetup()->create();
        $token = Password::broker()->createToken($user);

        $this->assertSame(10080, config('auth.passwords.users.expire'));
        $this->travel(8)->days();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newsecurepass',
            'password_confirmation' => 'newsecurepass',
        ])->assertSessionHasErrors('email');

        $this->assertSame('pending_setup', $user->refresh()->account_status);
        $this->assertNull($user->password_setup_completed_at);
    }

    public function test_role_specific_csv_and_excel_templates_exclude_credentials(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $csv = $this->actingAs($resLead)->get(route('res.users.import.template', [
            'format' => 'csv',
            'account_type' => 'reviewer',
        ]));
        $csv->assertOk();
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('reviewer_classification', $csvContent);
        $this->assertStringNotContainsString('reviewer_capacity', $csvContent);
        $this->assertStringNotContainsString('template_version', $csvContent);
        $this->assertStringNotContainsString('applicant_type', $csvContent);
        $this->assertStringContainsString('first_name,middle_name,last_name', $csvContent);
        $this->assertStringContainsString('lourdes.navarro@example.com', $csvContent);
        $this->assertStringNotContainsString('password', strtolower($csvContent));
        $this->assertStringNotContainsString('username', strtolower($csvContent));

        $xlsx = $this->actingAs($resLead)->get(route('res.users.import.template', [
            'format' => 'xlsx',
            'account_type' => 'student_researcher',
        ]))->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xlsx->headers->get('content-type'));
        $templatePath = $xlsx->baseResponse->getFile()->getPathname();
        $this->assertStringStartsWith('PK', (string) file_get_contents($templatePath));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($templatePath) === true);
        $this->assertStringContainsString('<cols>', (string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('<dataValidations', (string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('Institute of Engineering', (string) $zip->getFromName('xl/worksheets/sheet2.xml'));
        $this->assertStringContainsString('wrapText="1"', (string) $zip->getFromName('xl/styles.xml'));
        $zip->close();
    }

    public function test_csv_import_requires_preview_then_single_confirmation(): void
    {
        Notification::fake();
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $csv = implode("\n", [
            'first_name,middle_name,last_name,email,student_number,year_level,institution',
            'CSV,,Student,csv.student@ecrats.test,KLD-STU-601,Fourth Year,Institute of Engineering',
        ]);

        $response = $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);
        $response->assertOk()->assertSee('Import Preview')->assertSee('kld.stu.601.student');
        $this->assertDatabaseMissing('users', ['email' => 'csv.student@ecrats.test']);

        $previewFile = collect(Storage::disk('local')->allFiles('imports/user-accounts/previews/'.$resLead->id))->first();
        $this->assertNotNull($previewFile);
        $token = pathinfo($previewFile, PATHINFO_FILENAME);

        $this->actingAs($resLead)->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertRedirect(route('res.users.index'));
        $created = User::where('email', 'csv.student@ecrats.test')->firstOrFail();
        $this->assertSame('pending_setup', $created->account_status);
        Notification::assertSentTo($created, AccountSetupNotification::class);

        $this->actingAs($resLead)->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertSessionHasErrors('import_token');
        $this->assertSame(1, User::where('email', 'csv.student@ecrats.test')->count());
    }

    public function test_invalid_import_reports_rows_and_creates_nothing(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $csv = implode("\n", [
            'first_name,middle_name,last_name,email,employee_id,reviewer_classification',
            'Invalid,,Reviewer,not-an-email,KLD-EMP-701,unknown',
        ]);

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'reviewer',
            'accounts_file' => UploadedFile::fake()->createWithContent('reviewers.csv', $csv),
        ])->assertOk()->assertSee('Import cannot be confirmed')->assertSee('Row');

        $this->assertDatabaseMissing('users', ['institutional_identifier' => 'KLD-EMP-701']);
        $this->assertSame([], Storage::disk('local')->allFiles('imports/user-accounts/uploads'));
    }

    public function test_csv_import_rejects_invalid_utf8_beyond_the_initial_chunk(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $csv = 'first_name,middle_name,last_name,email,student_number,year_level'."\n"
            .str_repeat('A', 4100).",,Student,invalid@ecrats.test,KLD-STU-999,Fourth Year\xFF";

        $this->actingAs($resLead)
            ->from(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->post(route('res.users.import.store'), [
                'account_type' => 'student_researcher',
                'accounts_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('accounts_file');

        $this->assertDatabaseMissing('users', ['institutional_identifier' => 'KLD-STU-999']);
        $this->assertSame([], Storage::disk('local')->allFiles('imports/user-accounts/uploads'));
    }

    public function test_excel_import_round_trip_and_formula_rejection(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $headers = ['first_name', 'middle_name', 'last_name', 'email', 'student_number', 'year_level', 'institution'];
        $spreadsheet = app(SafeSpreadsheet::class);
        $validPath = $spreadsheet->createTemplate($headers);
        $this->appendSpreadsheetRow($validPath, [
            'Excel',
            '',
            'Student',
            'excel.student@ecrats.test',
            'KLD-STU-801',
            'Fourth Year',
            'Institute of Engineering',
        ]);

        $valid = new UploadedFile(
            $validPath,
            'students.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => $valid,
        ])->assertOk()->assertSee('Import Preview')->assertSee('excel.student@ecrats.test');

        $formulaPath = $spreadsheet->createTemplate($headers);
        $this->appendSpreadsheetRow($formulaPath, [
            'Unsafe',
            '',
            'Formula',
            'unsafe.formula@ecrats.test',
            'KLD-STU-802',
            'Fourth Year',
            'Institute of Engineering',
        ], 0);
        $formula = new UploadedFile(
            $formulaPath,
            'formula.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $this->actingAs($resLead)
            ->from(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->post(route('res.users.import.store'), [
                'account_type' => 'student_researcher',
                'accounts_file' => $formula,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('accounts_file');

        $this->assertDatabaseMissing('users', ['email' => 'unsafe.formula@ecrats.test']);
    }

    public function test_generated_example_row_is_ignored_during_csv_validation(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $template = $this->actingAs($resLead)->get(route('res.users.import.template', [
            'format' => 'csv',
            'account_type' => 'student_researcher',
        ]))->streamedContent();
        $csv = trim($template)."\n"
            .'Real,,Student,,real.student@ecrats.test,KLD-STU-901,,Fourth Year,Institute of Engineering,,';

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])
            ->assertOk()
            ->assertSee('real.student@ecrats.test')
            ->assertDontSee('alexandra.reyes@example.com');
    }

    public function test_duplicate_and_existing_csv_accounts_are_skipped_without_blocking_valid_rows(): void
    {
        Notification::fake();
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        User::factory()->create([
            'email' => 'existing.student@ecrats.test',
            'institutional_identifier' => 'KLD-STU-EXISTING',
        ]);
        $csv = implode("\n", [
            'first_name,middle_name,last_name,email,student_number,year_level,institution',
            'First,,Valid,first.valid@ecrats.test,KLD-STU-902,Fourth Year,Institute of Engineering',
            'First,,Valid,first.valid@ecrats.test,KLD-STU-902,Fourth Year,Institute of Engineering',
            'Existing,,Student,existing.student@ecrats.test,KLD-STU-EXISTING,Fourth Year,Institute of Engineering',
        ]);

        $this->actingAs($resLead)->post(route('res.users.import.store'), [
            'account_type' => 'student_researcher',
            'accounts_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])
            ->assertOk()
            ->assertSee('Skipped Rows')
            ->assertSee('only the first valid occurrence')
            ->assertSee('already exists')
            ->assertDontSee('Import cannot be confirmed');

        $previewFile = collect(Storage::disk('local')->allFiles('imports/user-accounts/previews/'.$resLead->id))->first();
        $token = pathinfo((string) $previewFile, PATHINFO_FILENAME);
        $this->actingAs($resLead)->post(route('res.users.import.confirm'), ['import_token' => $token])
            ->assertRedirect(route('res.users.index'));

        $this->assertSame(1, User::where('institutional_identifier', 'KLD-STU-902')->count());
        $this->assertSame(1, User::where('institutional_identifier', 'KLD-STU-EXISTING')->count());
    }

    public function test_res_lead_can_add_shared_dropdown_options_and_adviser_cannot(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);

        $this->actingAs($resLead)->post(route('res.users.profile-options.store'), [
            'option_field' => ProfileOptionField::Department->value,
            'option_value' => 'Computer Studies',
        ])->assertRedirect();

        $this->assertDatabaseHas('profile_options', [
            'field' => ProfileOptionField::Department->value,
            'value' => 'Computer Studies',
            'normalized_value' => 'computer studies',
            'created_by_user_id' => $resLead->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.profile_option_created',
            'actor_user_id' => $resLead->id,
        ]);

        $this->actingAs($resLead)
            ->get(route('res.users.create', ['mode' => 'individual', 'account_type' => 'student_researcher']))
            ->assertOk()
            ->assertSee('Computer Studies');

        $this->actingAs($adviser)->post(route('res.users.profile-options.store'), [
            'option_field' => ProfileOptionField::Program->value,
            'option_value' => 'Unauthorized Program',
        ])->assertRedirect();
        $this->assertDatabaseMissing('profile_options', ['value' => 'Unauthorized Program']);
    }

    public function test_audit_log_hides_completion_events_and_filters_by_actor_role(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'name' => 'Filtered Reviewer']);
        AuditLog::create([
            'actor_user_id' => $reviewer->id,
            'action' => 'user.profile_updated',
            'subject_type' => User::class,
            'subject_id' => $reviewer->id,
            'metadata' => ['result' => 'completed'],
        ]);
        AuditLog::create([
            'actor_user_id' => $reviewer->id,
            'action' => 'user.onboarding_completed',
            'subject_type' => User::class,
            'subject_id' => $reviewer->id,
            'metadata' => ['result' => 'completed'],
        ]);
        AuditLog::create([
            'actor_user_id' => $reviewer->id,
            'action' => 'user.password_setup_completed',
            'subject_type' => User::class,
            'subject_id' => $reviewer->id,
            'metadata' => ['result' => 'completed'],
        ]);

        $this->actingAs($resLead)->get(route('res.users.audit.index', [
            'search' => 'Filtered',
            'role' => UserRole::Reviewer->value,
            'result' => 'completed',
        ]))
            ->assertOk()
            ->assertSee('Filtered Reviewer')
            ->assertSee('Reviewer')
            ->assertSee('Profile Updated')
            ->assertDontSee('Onboarding Completed')
            ->assertDontSee('Password Setup Completed')
            ->assertDontSee('>Subject<', false);
    }

    public function test_revised_user_management_controls_use_csv_and_shared_ui_text(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->get(route('res.users.import.form', ['account_type' => 'student_researcher']))
            ->assertOk()
            ->assertSee('Upload CSV File')
            ->assertSee('>Validate<', false)
            ->assertSee('Show Errors')
            ->assertSee('No errors yet.')
            ->assertDontSee('Excel Template');

        $this->actingAs($resLead)
            ->get(route('res.users.index'))
            ->assertOk()
            ->assertSee('Apply Action')
            ->assertDontSee('Setup Email');
    }

    public function test_res_lead_can_mass_deactivate_and_soft_delete_accounts(): void
    {
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
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.archived', 'subject_id' => $archive->id]);
    }

    public function test_identity_correction_regenerates_username_notifies_user_and_audits_change(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $subject = User::factory()->create([
            'last_name' => 'Oldname',
            'institutional_identifier' => 'KLD-STU-901',
            'username' => 'kld.stu.901.oldname',
        ]);

        $this->actingAs($resLead)->patch(route('res.users.username', $subject), [
            'last_name' => 'Corrected Name',
            'institutional_identifier' => 'KLD-STU-902',
            'confirm_username_regeneration' => '1',
        ])->assertRedirect(route('res.users.show', $subject));

        $subject->refresh();
        $this->assertSame('kld.stu.902.corrected.name', $subject->username);
        $this->assertSame('Corrected Name', $subject->last_name);
        Notification::assertSentTo($subject, UsernameChangedNotification::class);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $resLead->id,
            'action' => 'user.username_regenerated',
            'subject_id' => $subject->id,
        ]);
    }

    public function test_setup_resend_is_rate_limited_and_never_duplicates_account(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $subject = User::factory()->pendingSetup()->create();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($resLead)
                ->post(route('res.users.password-reset', $subject))
                ->assertRedirect();
        }

        $this->actingAs($resLead)
            ->post(route('res.users.password-reset', $subject))
            ->assertTooManyRequests();

        $this->assertSame(1, User::whereKey($subject->id)->count());
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertCount(3, Notification::sent($subject, AccountSetupNotification::class));
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
            'reviewer_classification' => ReviewerClassification::Expedited->value,
            'reviewer_capacity' => 30,
            'role' => UserRole::Reviewer->value,
            'applicant_type' => null,
        ], $overrides);
    }

    /** @param array<int, string> $values */
    private function appendSpreadsheetRow(string $path, array $values, ?int $formulaColumn = null): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $cells = '';

        foreach ($values as $index => $value) {
            $column = chr(65 + $index);
            $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $cells .= $formulaColumn === $index
                ? '<c r="'.$column.'2"><f>1+1</f><v>2</v></c>'
                : '<c r="'.$column.'2" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
        }

        $sheet = str_replace('</sheetData>', '<row r="2">'.$cells.'</row></sheetData>', $sheet);
        $this->assertTrue($zip->addFromString('xl/worksheets/sheet1.xml', $sheet));
        $zip->close();
    }
}
