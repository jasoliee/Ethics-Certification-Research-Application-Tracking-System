<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\ReviewerConflict;
use App\Models\User;
use App\Services\Applications\ReviewerEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdviserReviewerCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_adviser_receives_reviewer_submenu_and_canonical_dashboard(): void
    {
        $adviser = User::factory()->reviewer(['Expedited', 'Full Board'])->create();

        $this->actingAs($adviser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reviewer Dashboard')
            ->assertSee(route('reviewer.dashboard'), false)
            ->assertSee(route('reviewer.assignments.index'), false);

        $this->actingAs($adviser)
            ->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('<h1>Reviewer Dashboard</h1>', false);
    }

    public function test_disabled_entitlement_is_enforced_against_an_existing_session_and_legacy_url(): void
    {
        $adviser = User::factory()->reviewer()->create();

        $this->actingAs($adviser)->get(route('reviewer.dashboard'))->assertOk();

        User::query()->whereKey($adviser->id)->update(['reviewer_enabled' => false]);

        $this->actingAs($adviser)->get(route('reviewer.dashboard'))->assertForbidden();
        $this->actingAs($adviser)->get('/reviewer/assignments')->assertForbidden();
    }

    public function test_authorized_legacy_get_redirects_to_equivalent_canonical_path(): void
    {
        $adviser = User::factory()->reviewer()->create();

        $this->actingAs($adviser)
            ->get('/reviewer/assignments?status=pending')
            ->assertRedirect(url('/adviser/reviewer/assignments?status=pending'));
    }

    public function test_assignment_policy_keeps_reviewer_work_owned_and_blind(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $otherReviewer = User::factory()->reviewer()->create();
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'application_status' => ApplicationStatus::UnderExpeditedReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'review_type' => ReviewType::Expedited,
            'submitted_at' => now(),
        ]);
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application,
            'reviewer_user_id' => $reviewer,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.show', $assignment))
            ->assertOk()
            ->assertDontSee($applicant->name)
            ->assertDontSee($adviser->name);

        $this->actingAs($otherReviewer)
            ->get(route('reviewer.assignments.show', $assignment))
            ->assertForbidden();
    }

    public function test_eligibility_ignores_legacy_classification_but_enforces_entitlement_and_relationship_conflicts(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'application_status' => ApplicationStatus::AwaitingReviewerAssignment,
            'current_stage' => ApplicationStage::EthicsReview,
            'review_type' => ReviewType::Expedited,
            'submitted_at' => now(),
        ]);
        $eligible = User::factory()->reviewer(['Expedited', 'Full Board'])->create();
        $wrongClassification = User::factory()->reviewer(['Full Board'])->create();
        $disabled = User::factory()->reviewer(['Expedited'])->create(['reviewer_enabled' => false]);
        $conflicted = User::factory()->reviewer(['Expedited'])->create();
        $endorser = User::factory()->reviewer(['Expedited'])->create();

        ReviewerConflict::create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $conflicted->id,
            'reason' => 'Declared professional conflict.',
            'declared_at' => now(),
        ]);
        Endorsement::create([
            'research_application_id' => $application->id,
            'adviser_user_id' => $endorser->id,
            'endorsement_status' => 'endorsed',
            'endorsed_at' => now(),
        ]);

        $ids = app(ReviewerEligibilityService::class)
            ->paginateCandidates($application, ReviewType::Expedited, [])
            ->getCollection()
            ->pluck('id');

        $this->assertTrue($ids->contains($eligible->id));
        $this->assertTrue($ids->contains($wrongClassification->id));
        $this->assertFalse($ids->contains($disabled->id));
        $this->assertFalse($ids->contains($conflicted->id));
        $this->assertFalse($ids->contains($endorser->id));
    }
}
