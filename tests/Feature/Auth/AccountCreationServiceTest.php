<?php

namespace Tests\Feature\Auth;

use App\Enums\ApplicantType;
use App\Enums\ReviewerClassification;
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

    public function test_res_lead_creates_only_approved_non_res_roles_as_pending_setup(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);

        $adviser = $service->create($resLead, $this->adviserAttributes());
        $reviewer = $service->create($resLead, $this->reviewerAttributes());
        $student = $service->create($resLead, $this->studentAttributes());
        $faculty = $service->create($resLead, $this->facultyAttributes());

        foreach ([$adviser, $reviewer, $student, $faculty] as $account) {
            $this->assertSame('pending_setup', $account->account_status);
            $this->assertNull($account->password_setup_completed_at);
            $this->assertSame($resLead->id, $account->created_by_user_id);
            $this->assertTrue(Hash::needsRehash($account->password) === false);
        }

        $this->assertSame(4, AuditLog::where('action', 'user.created')->count());
    }

    public function test_adviser_can_create_applicants_but_not_reviewer_or_res_lead(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $service = app(UserAccountService::class);
        $this->assertSame(UserRole::Applicant, $service->create($adviser, $this->studentAttributes())->role);

        foreach ([UserRole::Reviewer, UserRole::ResLead] as $role) {
            try {
                $service->create($adviser, [...$this->reviewerAttributes(), 'role' => $role]);
                $this->fail('The adviser created an unauthorized role.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_username_uses_normalized_identifier_and_surname_with_collision_suffix(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);
        $first = $service->create($resLead, $this->adviserAttributes([
            'institutional_identifier' => 'KLD-EMP-301',
            'last_name' => 'Reyes-Santos',
        ]));
        $second = $service->create($resLead, $this->adviserAttributes([
            'email' => 'second.adviser@ecrats.test',
            'institutional_identifier' => 'KLD.EMP.301',
            'last_name' => 'Reyes Santos',
        ]));

        $this->assertSame('kld.emp.301.reyes.santos', $first->username);
        $this->assertSame('kld.emp.301.reyes.santos2', $second->username);
        $this->assertLessThanOrEqual(30, strlen($second->username));
    }

    public function test_role_specific_required_fields_are_enforced(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);

        $this->expectValidation(fn () => $service->create($resLead, [...$this->studentAttributes(), 'year_level' => null]));
        $this->expectValidation(fn () => $service->create($resLead, [...$this->adviserAttributes(), 'position_title' => null]));
        $this->expectValidation(fn () => $service->create($resLead, [...$this->reviewerAttributes(), 'reviewer_classification' => null]));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function base(array $overrides): array
    {
        return array_merge([
            'first_name' => 'New',
            'middle_name' => null,
            'last_name' => 'User',
            'suffix' => null,
            'phone_number' => null,
            'institution' => 'Institute of Engineering',
            'department' => null,
        ], $overrides);
    }

    private function studentAttributes(array $overrides = []): array
    {
        return $this->base(array_merge([
            'email' => 'student@ecrats.test',
            'institutional_identifier' => 'KLD-STU-201',
            'program' => null,
            'year_level' => 'Fourth Year',
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
        ], $overrides));
    }

    private function facultyAttributes(array $overrides = []): array
    {
        return $this->base(array_merge([
            'email' => 'faculty@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-203',
            'program' => null,
            'position_title' => 'Faculty Researcher',
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Faculty,
        ], $overrides));
    }

    private function adviserAttributes(array $overrides = []): array
    {
        return $this->base(array_merge([
            'email' => 'adviser@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-201',
            'position_title' => 'Research Adviser',
            'role' => UserRole::Adviser,
            'applicant_type' => null,
        ], $overrides));
    }

    private function reviewerAttributes(array $overrides = []): array
    {
        return $this->base(array_merge([
            'email' => 'reviewer@ecrats.test',
            'institutional_identifier' => 'KLD-EMP-202',
            'position_title' => 'Faculty Reviewer',
            'reviewer_classification' => ReviewerClassification::Expedited,
            'reviewer_capacity' => 30,
            'role' => UserRole::Reviewer,
            'applicant_type' => null,
        ], $overrides));
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Validation should have failed.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
