<?php

namespace Tests\Feature\Auth;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Identity\UserAccountService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_lead_can_create_adviser_and_reviewer_accounts(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);

        $adviser = $service->create($resLead, $this->validAttributes([
            'username' => 'newadviser',
            'email' => 'newadviser@ecrats.test',
            'role' => UserRole::Adviser,
        ]));

        $reviewer = $service->create($resLead, $this->validAttributes([
            'username' => 'newreviewer',
            'email' => 'newreviewer@ecrats.test',
            'role' => UserRole::Reviewer,
        ]));

        $this->assertSame(UserRole::Adviser, $adviser->role);
        $this->assertSame(UserRole::Reviewer, $reviewer->role);
    }

    public function test_adviser_can_create_applicant_accounts_only(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $service = app(UserAccountService::class);

        $applicant = $service->create($adviser, $this->validAttributes([
            'username' => 'newapplicant',
            'email' => 'newapplicant@ecrats.test',
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Faculty,
        ]));

        $this->assertSame(UserRole::Applicant, $applicant->role);
        $this->assertSame(ApplicantType::Faculty, $applicant->applicant_type);
        $this->assertSame('Faculty Researcher', $applicant->displayRoleLabel());

        $this->expectException(AuthorizationException::class);

        $service->create($adviser, $this->validAttributes([
            'username' => 'blockedreviewer',
            'email' => 'blockedreviewer@ecrats.test',
            'role' => UserRole::Reviewer,
        ]));
    }

    public function test_applicant_and_reviewer_cannot_create_accounts(): void
    {
        $service = app(UserAccountService::class);
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);

        foreach ([$applicant, $reviewer] as $actor) {
            try {
                $service->create($actor, $this->validAttributes([
                    'username' => 'denied'.$actor->id,
                    'email' => 'denied'.$actor->id.'@ecrats.test',
                    'role' => UserRole::Applicant,
                ]));

                $this->fail('Account creation should have been denied.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_username_and_password_validation_boundaries_are_enforced(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);

        $created = $service->create($resLead, $this->validAttributes([
            'username' => str_repeat('a', 30),
            'email' => 'valid30@ecrats.test',
            'password' => '12345678',
            'role' => UserRole::Reviewer,
        ]));

        $this->assertSame(str_repeat('a', 30), $created->username);

        $created = $service->create($resLead, $this->validAttributes([
            'username' => 'validsixteen',
            'email' => 'validsixteen@ecrats.test',
            'password' => '1234567890123456',
            'role' => UserRole::Reviewer,
        ]));

        $this->assertSame('validsixteen', $created->username);

        $this->expectValidationException(fn () => $service->create($resLead, $this->validAttributes([
            'username' => str_repeat('b', 31),
            'email' => 'toolong@ecrats.test',
            'role' => UserRole::Reviewer,
        ])));

        $this->expectValidationException(fn () => $service->create($resLead, $this->validAttributes([
            'username' => 'shortpass',
            'email' => 'shortpass@ecrats.test',
            'password' => '1234567',
            'role' => UserRole::Reviewer,
        ])));

        $this->expectValidationException(fn () => $service->create($resLead, $this->validAttributes([
            'username' => 'longpass',
            'email' => 'longpass@ecrats.test',
            'password' => '12345678901234567',
            'role' => UserRole::Reviewer,
        ])));
    }

    public function test_duplicate_username_is_rejected_and_usernames_are_trimmed(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $service = app(UserAccountService::class);

        $user = $service->create($resLead, $this->validAttributes([
            'username' => ' trimmeduser ',
            'email' => 'trimmeduser@ecrats.test',
            'role' => UserRole::Reviewer,
        ]));

        $this->assertSame('trimmeduser', $user->username);

        $this->expectValidationException(fn () => $service->create($resLead, $this->validAttributes([
            'username' => 'trimmeduser',
            'email' => 'duplicate@ecrats.test',
            'role' => UserRole::Reviewer,
        ])));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAttributes(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New User',
            'username' => 'newuser',
            'email' => 'newuser@ecrats.test',
            'password' => '12345678',
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
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
