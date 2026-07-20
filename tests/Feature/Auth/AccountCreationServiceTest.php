<?php

namespace Tests\Feature\Auth;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Identity\UserAccountService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_lead_can_create_every_approved_non_res_lead_account_type(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);

        $accounts = [
            [UserRole::Adviser, null, 'adviser@ecrats.test', 'KLD-EMP-201'],
            [UserRole::Reviewer, null, 'reviewer@ecrats.test', 'KLD-EMP-202'],
            [UserRole::Applicant, ApplicantType::Student, 'student@ecrats.test', 'KLD-STU-201'],
            [UserRole::Applicant, ApplicantType::Faculty, 'faculty@ecrats.test', 'KLD-EMP-203'],
        ];

        foreach ($accounts as $index => [$role, $applicantType, $email, $identifier]) {
            $user = $service->create($resLead, $this->validAttributes([
                'first_name' => 'Account',
                'last_name' => 'User'.($index + 1),
                'email' => $email,
                'institutional_identifier' => $identifier,
                'role' => $role,
                'applicant_type' => $applicantType,
                'username' => 'request-cannot-choose-this',
            ]));

            $this->assertSame($role, $user->role);
            $this->assertSame($applicantType, $user->applicant_type);
            $this->assertSame($resLead->id, $user->created_by_user_id);
            $this->assertNotSame('request-cannot-choose-this', $user->username);
            $this->assertTrue(Hash::check('securepass', $user->password));
        }

        $this->assertSame(4, AuditLog::where('action', 'user.created')->count());
    }

    public function test_adviser_can_create_student_and_faculty_accounts_only(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $service = app(UserAccountService::class);

        foreach ([ApplicantType::Student, ApplicantType::Faculty] as $index => $type) {
            $applicant = $service->create($adviser, $this->validAttributes([
                'email' => "applicant{$index}@ecrats.test",
                'institutional_identifier' => "KLD-APP-20{$index}",
                'role' => UserRole::Applicant,
                'applicant_type' => $type,
            ]));

            $this->assertSame(UserRole::Applicant, $applicant->role);
            $this->assertSame($type, $applicant->applicant_type);
        }

        $this->expectException(AuthorizationException::class);
        $service->create($adviser, $this->validAttributes([
            'role' => UserRole::Reviewer,
            'email' => 'blocked-reviewer@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-299',
        ]));
    }

    public function test_no_user_can_create_a_res_lead_account(): void
    {
        $service = app(UserAccountService::class);

        foreach ([UserRole::ResLead, UserRole::Adviser, UserRole::Applicant, UserRole::Reviewer] as $actorRole) {
            $actor = User::factory()->create(['role' => $actorRole]);

            try {
                $service->create($actor, $this->validAttributes([
                    'email' => 'blocked'.$actor->id.'@ecrats.test',
                    'institutional_identifier' => 'KLD-BLOCK-'.$actor->id,
                    'role' => UserRole::ResLead,
                ]));
                $this->fail('RES Lead account creation should always be denied.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_usernames_are_generated_normalized_bounded_and_collision_safe(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);

        $first = $service->create($resLead, $this->validAttributes([
            'first_name' => 'Ana Maria',
            'last_name' => 'Reyes-Santos',
            'email' => 'ana.one@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-301',
            'role' => UserRole::Adviser,
        ]));
        $second = $service->create($resLead, $this->validAttributes([
            'first_name' => 'Ana Maria',
            'last_name' => 'Reyes-Santos',
            'email' => 'ana.two@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-302',
            'role' => UserRole::Adviser,
        ]));

        $this->assertSame('ana.maria.reyes.santos_adviser', $first->username);
        $this->assertSame('ana.maria.reyes.santos_advise2', $second->username);
        $this->assertLessThanOrEqual(30, strlen($second->username));
    }

    public function test_password_minimum_and_reasonable_storage_boundary_are_enforced(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);

        $service->create($resLead, $this->validAttributes([
            'password' => str_repeat('a', 64),
            'password_confirmation' => str_repeat('a', 64),
        ]));

        $this->expectValidationException(fn () => $service->create($resLead, $this->validAttributes([
            'email' => 'short@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-402',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ])));
        $this->expectValidationException(fn () => $service->create($resLead, $this->validAttributes([
            'email' => 'long@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-403',
            'password' => str_repeat('b', 65),
            'password_confirmation' => str_repeat('b', 65),
        ])));
    }

    public function test_email_and_institutional_identifier_must_be_unique(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);
        $service->create($resLead, $this->validAttributes());

        $this->expectValidationException(fn () => $service->create($resLead, $this->validAttributes([
            'email' => 'NEW.USER@ECRATS.TEST',
            'institutional_identifier' => 'KLD-EMP-999',
        ])));
        $this->expectValidationException(fn () => $service->create($resLead, $this->validAttributes([
            'email' => 'another@ecrats.test',
            'institutional_identifier' => 'kld-emp-401',
        ])));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function validAttributes(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New',
            'middle_name' => null,
            'last_name' => 'User',
            'suffix' => null,
            'email' => 'new.user@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-401',
            'phone_number' => '+63 917 123 4567',
            'institution' => 'Kolehiyo ng Lungsod ng Dasmarinas',
            'department' => 'Research Ethics Section',
            'position_title' => 'Research Staff',
            'password' => 'securepass',
            'password_confirmation' => 'securepass',
            'role' => UserRole::Reviewer,
            'applicant_type' => null,
        ], $overrides);
    }

    private function expectValidationException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Validation should have failed.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
