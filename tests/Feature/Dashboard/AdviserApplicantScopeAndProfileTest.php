<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Services\Applications\AdviserEndorsementStatisticsService;
use App\Services\Applications\ReviewerCapabilityProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdviserApplicantScopeAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_applicant_scope_requires_creation_or_formal_submission_everywhere(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $otherAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        $createdApplicant = User::factory()->create([
            'name' => 'Created Visible Applicant',
            'created_by_user_id' => $adviser->id,
        ]);
        $submittedApplicant = User::factory()->create([
            'name' => 'Submitted Visible Applicant',
            'created_by_user_id' => $otherAdviser->id,
        ]);
        ResearchApplication::factory()->submittedToAdviser($adviser)->create([
            'applicant_user_id' => $submittedApplicant->id,
        ]);
        $historicalApplicant = User::factory()->create([
            'name' => 'Historical Submitted Applicant',
            'created_by_user_id' => $otherAdviser->id,
        ]);
        ResearchApplication::factory()->create([
            'applicant_user_id' => $historicalApplicant->id,
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::Archived,
            'submitted_at' => now()->subMonth(),
        ]);

        $draftOnlyApplicant = User::factory()->create([
            'name' => 'Private Draft Applicant',
            'created_by_user_id' => $otherAdviser->id,
        ]);
        ResearchApplication::factory()->create([
            'applicant_user_id' => $draftOnlyApplicant->id,
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::Draft,
            'submitted_at' => null,
        ]);
        $unrelatedApplicant = User::factory()->create(['name' => 'Unrelated Private Applicant']);

        $list = $this->actingAs($adviser)
            ->get(route('adviser.applicants.index'))
            ->assertOk()
            ->assertSee($createdApplicant->name)
            ->assertSee($submittedApplicant->name)
            ->assertSee($historicalApplicant->name)
            ->assertDontSee($draftOnlyApplicant->name)
            ->assertDontSee($unrelatedApplicant->name);
        $this->assertStringNotContainsString($unrelatedApplicant->email, $list->getContent());

        $this->actingAs($adviser)
            ->get(route('adviser.applicants.index', ['search' => $unrelatedApplicant->name]))
            ->assertOk()
            ->assertSee('Showing 0 users')
            ->assertDontSee($unrelatedApplicant->email)
            ->assertDontSee($unrelatedApplicant->institutional_identifier);

        User::factory()->count(8)->create(['created_by_user_id' => $adviser->id]);
        $this->actingAs($adviser)
            ->get(route('adviser.applicants.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('of 11 users')
            ->assertDontSee($draftOnlyApplicant->name)
            ->assertDontSee($unrelatedApplicant->name);

        foreach ([$createdApplicant, $submittedApplicant, $historicalApplicant] as $visibleApplicant) {
            $this->actingAs($adviser)
                ->get(route('adviser.applicants.show', $visibleApplicant))
                ->assertOk();
        }

        foreach ([$draftOnlyApplicant, $unrelatedApplicant] as $privateApplicant) {
            $this->actingAs($adviser)
                ->get(route('adviser.applicants.show', $privateApplicant))
                ->assertForbidden();
            $this->actingAs($adviser)
                ->get(route('adviser.applicants.edit', $privateApplicant))
                ->assertForbidden();
        }

        $this->actingAs($adviser)->get('/adviser/applicants/export')->assertNotFound();
        $this->actingAs($adviser)->getJson('/api/adviser/applicants')->assertNotFound();
    }

    public function test_applicant_management_and_profile_show_the_same_live_endorsement_statistics(): void
    {
        $adviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'expected_endorsement_count' => 10,
        ]);

        $endorsedApplications = collect(range(1, 3))->map(fn (): ResearchApplication => ResearchApplication::factory()->create([
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::UnderResScreening,
            'current_stage' => ApplicationStage::ResScreening,
            'submitted_at' => now()->subDays(2),
        ]));
        foreach ($endorsedApplications as $application) {
            Endorsement::query()->create([
                'research_application_id' => $application->id,
                'adviser_user_id' => $adviser->id,
                'endorsement_status' => EndorsementStatus::Endorsed,
                'endorsed_at' => now()->subDay(),
            ]);
        }
        // Immutable history may contain multiple actions; one application still counts once.
        Endorsement::query()->create([
            'research_application_id' => $endorsedApplications->first()->id,
            'adviser_user_id' => $adviser->id,
            'endorsement_status' => EndorsementStatus::Endorsed,
            'endorsed_at' => now(),
        ]);

        ResearchApplication::factory()->count(2)->submittedToAdviser($adviser)->create();
        ResearchApplication::factory()->create([
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::SubmittedToAdviser,
            'submitted_at' => null,
        ]);

        $statistics = app(AdviserEndorsementStatisticsService::class)->for($adviser);
        $this->assertSame([
            'declared' => 10,
            'endorsed' => 3,
            'awaiting' => 2,
            'remaining' => 7,
            'not_received' => 5,
        ], $statistics);

        $this->actingAs($adviser)
            ->get(route('adviser.applicants.index'))
            ->assertOk()
            ->assertSee('aria-label="Declared Expected: 10"', false)
            ->assertSee('aria-label="Successfully Endorsed: 3"', false)
            ->assertSee('aria-label="Received, Awaiting Endorsement: 2"', false)
            ->assertSee('aria-label="Remaining Expected Total: 7"', false)
            ->assertSee('aria-label="Not Yet Received: 5"', false);

        $this->actingAs($adviser)
            ->get(route('adviser.profile.show'))
            ->assertOk()
            ->assertSee('Declared Expected Endorsements')
            ->assertSee('Successfully Endorsed')
            ->assertSee('Received, Awaiting Endorsement')
            ->assertSee('Remaining Expected Total')
            ->assertSee('Not Yet Received');
    }

    public function test_adviser_profile_includes_complete_setup_aware_reviewer_capability(): void
    {
        $adviser = User::factory()->reviewer(['Expedited', 'Full Board'])->create([
            'reviewer_capacity' => 4,
        ]);
        ReviewerAssignment::factory()->count(2)->create([
            'reviewer_user_id' => $adviser->id,
            'assignment_status' => ReviewerAssignmentStatus::InReview,
        ]);
        ReviewerAssignment::factory()->create([
            'reviewer_user_id' => $adviser->id,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now(),
        ]);

        $profile = app(ReviewerCapabilityProfileService::class)->for($adviser);
        $this->assertTrue($profile['enabled']);
        $this->assertSame(30, $profile['capacity']);
        $this->assertSame(2, $profile['active_load']);
        $this->assertSame(28, $profile['available_capacity']);
        $this->assertTrue($profile['eligible']);
        $this->assertSame('Eligible for assignment', $profile['eligibility_label']);

        $this->actingAs($adviser)
            ->get(route('adviser.profile.show'))
            ->assertOk()
            ->assertSee('Reviewer Access')
            ->assertDontSee('Permitted Classifications')
            ->assertSee('Maximum Active Application Load')
            ->assertSee('Current Active Assignment Load')
            ->assertSee('Available Capacity')
            ->assertSee('Assignment Eligibility')
            ->assertSee('Eligible for assignment');

        $adviser->forceFill(['password_setup_completed_at' => null])->save();
        $notReady = app(ReviewerCapabilityProfileService::class)->for($adviser->refresh());
        $this->assertFalse($notReady['eligible']);
        $this->assertSame('Account setup incomplete', $notReady['eligibility_label']);
    }
}
