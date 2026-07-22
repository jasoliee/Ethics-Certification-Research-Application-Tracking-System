<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_login_guide_is_role_specific_and_requires_completion(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-requires-completion="true"', false)
            ->assertSee('Student Researcher Guide')
            ->assertSee('Set up and sign in')
            ->assertSee('data-guide-open', false)
            ->assertSee('hidden >', false);

        $reviewer = User::factory()->create([
            'role' => UserRole::Reviewer,
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($reviewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ethics Reviewer Guide')
            ->assertSee('protect applicant and reviewer confidentiality');
    }

    public function test_completion_updates_only_current_user_and_is_idempotent(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);
        $other = User::factory()->create(['onboarding_completed_at' => null]);

        $this->actingAs($user)
            ->postJson(route('onboarding.complete'))
            ->assertOk()
            ->assertJson(['completed' => true]);

        $this->actingAs($user)
            ->postJson(route('onboarding.complete'))
            ->assertOk();

        $this->assertNotNull($user->refresh()->onboarding_completed_at);
        $this->assertNull($other->refresh()->onboarding_completed_at);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $user->id,
            'action' => 'user.onboarding_completed',
            'subject_id' => $user->id,
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-requires-completion="false"', false)
            ->assertSee('data-guide-open >', false);
    }

    public function test_guest_cannot_complete_onboarding(): void
    {
        $this->post(route('onboarding.complete'))->assertRedirect(route('login'));
    }
}
