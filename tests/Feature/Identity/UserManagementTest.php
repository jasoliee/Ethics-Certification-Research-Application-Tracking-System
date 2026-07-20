<?php

namespace Tests\Feature\Identity;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_lead_listing_is_searchable_paginated_and_excludes_res_lead_accounts(): void
    {
        $resLead = User::factory()->create([
            'role' => UserRole::ResLead,
            'name' => 'Primary RES Lead',
            'institutional_identifier' => 'RES-LEAD-NOT-IN-TABLE',
        ]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser, 'name' => 'Ana Reyes', 'email' => 'ana.reyes@ecrats.test']);
        User::factory()->count(11)->create(['role' => UserRole::Reviewer]);

        $this->actingAs($resLead)
            ->get(route('res.users.index'))
            ->assertOk()
            ->assertSee('User Management')
            ->assertSee('Showing 1 to 10 of 12 users')
            ->assertSee('Ana Reyes')
            ->assertDontSee('RES-LEAD-NOT-IN-TABLE');

        $this->actingAs($resLead)
            ->get(route('res.users.index', ['search' => 'ana.reyes@ecrats.test']))
            ->assertOk()
            ->assertSee($adviser->institutional_identifier)
            ->assertSee('Showing 1 to 1 of 1 users');
    }

    public function test_adviser_sees_only_applicants_with_an_allowed_relationship(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $owned = User::factory()->create(['role' => UserRole::Applicant, 'created_by_user_id' => $adviser->id, 'name' => 'Owned Applicant']);
        $other = User::factory()->create(['role' => UserRole::Applicant, 'name' => 'Other Applicant']);
        User::factory()->create(['role' => UserRole::Reviewer, 'name' => 'Hidden Reviewer']);

        $this->actingAs($adviser)
            ->get(route('adviser.applicants.index'))
            ->assertOk()
            ->assertSee($owned->name)
            ->assertDontSee($other->name)
            ->assertDontSee('Hidden Reviewer');

        $this->actingAs($adviser)
            ->get(route('adviser.applicants.show', $other))
            ->assertForbidden();
    }

    public function test_res_lead_creates_account_with_normalized_fields_and_generated_username(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $response = $this->actingAs($resLead)->post(route('res.users.store'), $this->validPayload([
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'JUAN.DELA.CRUZ@ECRATS.TEST',
            'institutional_identifier' => 'kld-stu-501',
            'role' => UserRole::Applicant->value,
            'applicant_type' => ApplicantType::Student->value,
            'username' => 'manual-username',
        ]));

        $user = User::where('email', 'juan.dela.cruz@ecrats.test')->firstOrFail();
        $response->assertRedirect(route('res.users.show', ['managedUser' => $user, 'created' => 1]));
        $this->assertSame('Juan Santos Dela Cruz', $user->name);
        $this->assertSame('KLD-STU-501', $user->institutional_identifier);
        $this->assertSame('juan.dela.cruz_student', $user->username);
        $this->assertSame($resLead->id, $user->created_by_user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'subject_id' => $user->id]);
    }

    public function test_adviser_cannot_create_reviewer_or_res_lead_accounts(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);

        foreach ([UserRole::Reviewer, UserRole::ResLead] as $role) {
            $this->actingAs($adviser)
                ->post(route('adviser.applicants.store'), $this->validPayload([
                    'email' => $role->value.'@ecrats.test',
                    'institutional_identifier' => 'KLD-BLOCK-'.substr(md5($role->value), 0, 6),
                    'role' => $role->value,
                ]))
                ->assertForbidden();
        }
    }

    public function test_profile_update_cannot_change_role_username_status_or_password(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $subject = User::factory()->create(['role' => UserRole::Reviewer, 'account_status' => 'inactive']);
        $originalPassword = $subject->password;
        $originalUsername = $subject->username;

        $this->actingAs($resLead)
            ->put(route('res.users.update', $subject), [
                ...$this->profilePayload($subject),
                'first_name' => 'Updated',
                'role' => UserRole::ResLead->value,
                'username' => 'changed-by-payload',
                'account_status' => 'active',
                'password' => 'changed-password',
            ])
            ->assertRedirect(route('res.users.show', $subject));

        $subject->refresh();
        $this->assertSame('Updated', $subject->first_name);
        $this->assertSame(UserRole::Reviewer, $subject->role);
        $this->assertSame($originalUsername, $subject->username);
        $this->assertSame('inactive', $subject->account_status);
        $this->assertSame($originalPassword, $subject->password);
    }

    public function test_res_lead_can_deactivate_account_and_inactive_login_uses_generic_error(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $subject = User::factory()->create([
            'role' => UserRole::Reviewer,
            'username' => 'statusreviewer',
            'password' => Hash::make('securepass'),
        ]);

        $this->actingAs($resLead)
            ->patch(route('res.users.status', $subject), ['account_status' => 'inactive'])
            ->assertRedirect();

        $this->assertSame('inactive', $subject->refresh()->account_status);
        $this->post(route('logout'));
        $this->from('/login')->post('/login', ['username' => 'statusreviewer', 'password' => 'securepass'])
            ->assertSessionHasErrors(['credentials' => 'The username or password is incorrect.']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.status_changed', 'subject_id' => $subject->id]);
    }

    public function test_res_lead_sends_secure_reset_link_and_user_can_complete_it(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $subject = User::factory()->create(['role' => UserRole::Reviewer]);

        $this->actingAs($resLead)
            ->post(route('res.users.password-reset', $subject))
            ->assertRedirect();

        Notification::assertSentTo($subject, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset_requested', 'subject_id' => $subject->id]);

        $token = Password::broker()->createToken($subject);
        $this->post(route('logout'));
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $subject->email,
            'password' => 'newsecurepass',
            'password_confirmation' => 'newsecurepass',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('newsecurepass', $subject->refresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset_completed', 'subject_id' => $subject->id]);
    }

    public function test_valid_csv_import_is_atomic_audited_and_removes_private_temporary_file(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $csv = implode("\n", [
            'account_type,first_name,middle_name,last_name,suffix,email,institutional_identifier,phone_number,institution,department,position_title,password',
            'student_researcher,CSV,,Student,,csv.student@ecrats.test,KLD-STU-601,,KLD,Engineering,,securepass',
            'reviewer,CSV,,Reviewer,,csv.reviewer@ecrats.test,KLD-EMP-601,,KLD,Research Ethics,,securepass',
        ]);

        $this->actingAs($resLead)
            ->post(route('res.users.import.store'), [
                'accounts_file' => UploadedFile::fake()->createWithContent('accounts.csv', $csv),
            ])
            ->assertRedirect(route('res.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'csv.student@ecrats.test', 'applicant_type' => ApplicantType::Student->value]);
        $this->assertDatabaseHas('users', ['email' => 'csv.reviewer@ecrats.test', 'role' => UserRole::Reviewer->value]);
        $this->assertSame([], Storage::disk('local')->allFiles('imports/user-accounts'));
        $this->assertSame(1, AuditLog::where('action', 'user.bulk_imported')->count());
    }

    public function test_invalid_csv_rolls_back_every_row_and_deletes_temporary_file(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $csv = implode("\n", [
            'account_type,first_name,last_name,email,institutional_identifier,password',
            'student_researcher,Valid,Student,valid.csv@ecrats.test,KLD-STU-701,securepass',
            'reviewer,Invalid,Reviewer,not-an-email,KLD-EMP-701,short',
        ]);

        $this->actingAs($resLead)
            ->from(route('res.users.import.form'))
            ->post(route('res.users.import.store'), [
                'accounts_file' => UploadedFile::fake()->createWithContent('accounts.csv', $csv),
            ])
            ->assertRedirect(route('res.users.import.form'))
            ->assertSessionHasErrors('accounts_file');

        $this->assertDatabaseMissing('users', ['email' => 'valid.csv@ecrats.test']);
        $this->assertSame([], Storage::disk('local')->allFiles('imports/user-accounts'));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New',
            'middle_name' => null,
            'last_name' => 'Account',
            'suffix' => null,
            'email' => 'new.account@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-501',
            'phone_number' => '+63 917 123 4567',
            'institution' => 'Kolehiyo ng Lungsod ng Dasmarinas',
            'department' => 'Research Ethics Section',
            'position_title' => 'Research Staff',
            'password' => 'securepass',
            'password_confirmation' => 'securepass',
            'role' => UserRole::Reviewer->value,
            'applicant_type' => null,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function profilePayload(User $user): array
    {
        return [
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'suffix' => $user->suffix,
            'email' => $user->email,
            'institutional_identifier' => $user->institutional_identifier,
            'phone_number' => $user->phone_number,
            'institution' => $user->institution,
            'department' => $user->department,
            'position_title' => $user->position_title,
        ];
    }
}
