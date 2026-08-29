<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\DeadlineManualStatus;
use App\Enums\RequirementStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\ApplicationDecisionRelease;
use App\Models\ApplicationDocument;
use App\Models\ApplicationRevision;
use App\Models\ApplicationScreening;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\TimelineCalendarEvent;
use App\Models\User;
use App\Services\Dashboard\DashboardDataService;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_dashboard_displays_its_database_driven_empty_state(): void
    {
        $cases = [
            UserRole::Applicant->value => 'No application yet',
            UserRole::Adviser->value => 'No submitted applications yet',
            UserRole::ResLead->value => 'No pending administrative actions',
        ];

        foreach ($cases as $role => $emptyText) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee($emptyText)
                ->assertSee('data-menu-toggle="notifications"', false)
                ->assertSee('data-menu-toggle="profile"', false);
        }

        $reviewerAdviser = User::factory()->reviewer()->create();
        $this->actingAs($reviewerAdviser)
            ->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('No assigned applications yet');
    }

    /**
     * Verify every role using shared summary cards renders zero counts in icon, number, then label order.
     */
    public function test_shared_summary_cards_render_left_icon_and_right_copy_order_for_zero_counts(): void
    {
        // Arrange the first expected card for each dashboard role that owns a summary-card grid.
        $cases = [
            UserRole::Adviser->value => 'Pending',
            UserRole::ResLead->value => 'For REU Screening',
        ];

        foreach ($cases as $role => $label) {
            // Act with an empty database scope so the shared component must render a real zero-count state.
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $content = (string) $response->getContent();
            $cardStart = strpos($content, 'aria-label="'.$label.': 0"');
            $cardEnd = $cardStart === false ? false : strpos($content, '</a>', $cardStart);
            $this->assertIsInt($cardStart);
            $this->assertIsInt($cardEnd);
            $card = substr($content, $cardStart, $cardEnd - $cardStart);

            // Assert accessible labeling and the left-icon then right-copy semantic source order.
            $response->assertSee('aria-label="'.$label.': 0"', false);
            $iconPosition = strpos($card, 'dashboard-summary-icon');
            $countPosition = strpos($card, '<strong>0</strong>');
            $labelPosition = strpos($card, '<span>'.$label.'</span>');
            $this->assertIsInt($iconPosition);
            $this->assertIsInt($countPosition);
            $this->assertIsInt($labelPosition);
            $this->assertLessThan($countPosition, $iconPosition);
            $this->assertLessThan($labelPosition, $countPosition);
        }

        $reviewer = User::factory()->reviewer()->create();
        $this->actingAs($reviewer)
            ->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Pending Reviews: 0"', false);
    }

    public function test_applicant_dashboard_displays_active_application_requirements_and_milestone(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'application_code' => 'ECRATS-TEST-0001',
            'applicant_user_id' => $applicant->id,
            'adviser_user_id' => $adviser->id,
            'research_title' => 'Ethical Use of Learning Analytics',
            'application_status' => ApplicationStatus::UnderResScreening,
            'current_stage' => ApplicationStage::ResScreening,
            'submitted_at' => now()->subDay(),
            'status_updated_at' => now(),
        ]);

        $proposal = DocumentRequirement::create([
            'code' => 'PROPOSAL',
            'name' => 'Research Proposal',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        DocumentRequirement::create([
            'code' => 'CONSENT',
            'name' => 'Informed Consent',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $proposal->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => 'proposal.pdf',
            'stored_file_path' => 'tests/proposal.pdf',
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now(),
        ]);
        DeadlineConfiguration::create([
            'deadline_key' => 'applicant-test-application-submission',
            'title' => 'Application submission deadline',
            'audience_role' => UserRole::Applicant,
            'due_at' => now()->addDays(2),
            'priority' => 5,
            'is_active' => true,
        ]);
        TimelineCalendarEvent::create([
            'milestone_key' => 'test-screening',
            'label' => 'REU Screening',
            'term_label' => '1st Semester, A.Y. 2026-2027',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ECRATS-TEST-0001')
            ->assertSee('Ethical Use of Learning Analytics')
            ->assertSee('Under REU Screening')
            ->assertSee('1 of 2 mandatory completed')
            ->assertDontSee('Application submission deadline')
            ->assertSee('1st Semester, A.Y. 2026-2027')
            ->assertSee('dashboard-panel-header-meta', false)
            ->assertSee('data-research-title-tooltip', false)
            ->assertDontSee('No application yet');
    }

    public function test_applicant_dashboard_selects_the_newest_record_by_creation_not_a_later_edit(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $currentTerm = AcademicTerm::create([
            'semester' => 'Current Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(4),
            'is_active' => true,
        ]);
        ResearchApplication::factory()->create([
            'application_code' => 'OLDER-EDITED-LATER',
            'applicant_user_id' => $applicant,
            'academic_term_id' => $currentTerm,
            'research_title' => 'Older Application Edited Later',
            'created_at' => now()->subDays(4),
            'updated_at' => now(),
        ]);
        ResearchApplication::factory()->create([
            'application_code' => 'NEWEST-CREATED',
            'applicant_user_id' => $applicant,
            'academic_term_id' => $currentTerm,
            'research_title' => 'Newest Created Application',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('NEWEST-CREATED')
            ->assertSee('Newest Created Application')
            ->assertDontSee('OLDER-EDITED-LATER')
            ->assertDontSee('Older Application Edited Later');
    }

    public function test_applicant_timeline_uses_canonical_keys_and_the_current_term_even_with_a_historical_application(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $applicationTerm = AcademicTerm::create([
            'semester' => 'Historical Term',
            'academic_year' => '2025-2026',
            'starts_at' => now()->subYear(),
            'ends_at' => now()->subMonths(7),
            'is_active' => false,
        ]);
        $currentTerm = AcademicTerm::create([
            'semester' => 'Current Term',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(4),
            'is_active' => true,
        ]);
        ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'academic_term_id' => $applicationTerm,
            'application_status' => ApplicationStatus::UnderResScreening,
            'current_stage' => ApplicationStage::ResScreening,
            'submitted_at' => now()->subMonths(8),
        ]);

        TimelineCalendarEvent::create([
            'academic_term_id' => $applicationTerm->id,
            'milestone_key' => "term-{$applicationTerm->id}-res-screening",
            'label' => 'Historical REU Screening Window',
            'term_label' => $applicationTerm->label(),
            'starts_at' => now()->subMonths(9),
            'ends_at' => now()->subMonths(8),
            'sort_order' => 99,
            'is_active' => true,
        ]);
        TimelineCalendarEvent::create([
            'academic_term_id' => $applicationTerm->id,
            'milestone_key' => "term-{$applicationTerm->id}-submission",
            'label' => 'Historical Submission Window',
            'term_label' => $applicationTerm->label(),
            'starts_at' => now()->subMonths(11),
            'ends_at' => now()->subMonths(10),
            'sort_order' => 100,
            'is_active' => true,
        ]);
        TimelineCalendarEvent::create([
            'academic_term_id' => $currentTerm->id,
            'milestone_key' => "term-{$currentTerm->id}-res-screening",
            'label' => 'Wrong Current-Term Screening',
            'term_label' => $currentTerm->label(),
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($currentTerm->label())
            ->assertSee('Wrong Current-Term Screening')
            ->assertSee(now()->format('M j, Y').' - '.now()->addDay()->format('M j, Y'))
            ->assertDontSee('Historical Submission Window')
            ->assertDontSee('Historical REU Screening Window');
    }

    public function test_applicant_deadline_alert_uses_active_term_dates_and_manual_availability(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $term = AcademicTerm::create([
            'semester' => '1st Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(4),
            'is_active' => true,
        ]);
        ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'academic_term_id' => $term,
        ]);
        $deadline = DeadlineConfiguration::create([
            'academic_term_id' => $term->id,
            'deadline_key' => "term-{$term->id}-application-submission",
            'title' => 'Application Submission Deadline',
            'audience_role' => UserRole::Applicant,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDays(2),
            'manual_status' => DeadlineManualStatus::Closed,
            'priority' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Submission closed')
            ->assertSee('currently closed by the REU Lead');

        $deadline->update([
            'starts_at' => now()->addDay(),
            'due_at' => now()->addDays(3),
            'manual_status' => DeadlineManualStatus::Open,
        ]);
        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('days remaining')
            ->assertSee('Application submission is manually open by the REU Lead.');

        $deadline->update([
            'starts_at' => now()->subDays(3),
            'due_at' => now()->subMinute(),
            'manual_status' => null,
        ]);
        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Submission closed')
            ->assertSee('Application submission closed on');
    }

    public function test_adviser_dashboard_counts_and_table_are_scoped_to_the_logged_in_adviser(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $otherAdviser = User::factory()->create(['role' => UserRole::Adviser]);

        $this->applicationForAdviser($adviser, 'ADV-001', ApplicationStatus::SubmittedToAdviser);
        $this->applicationForAdviser($adviser, 'ADV-002', ApplicationStatus::UnderExpeditedReview);
        $this->applicationForAdviser($adviser, 'ADV-003', ApplicationStatus::ReturnedByAdviser);
        $this->applicationForAdviser($otherAdviser, 'OTHER-001', ApplicationStatus::SubmittedToAdviser);

        $this->actingAs($adviser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Pending: 1"', false)
            ->assertSee('aria-label="Endorsed: 1"', false)
            ->assertSee('aria-label="Returned: 1"', false)
            ->assertDontSee('In Review')
            ->assertSee('ADV-001')
            ->assertDontSee('OTHER-001');
    }

    public function test_adviser_and_res_dashboards_exclude_applications_without_the_current_term_link(): void
    {
        AcademicTerm::create([
            'semester' => 'Current Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(4),
            'is_active' => true,
        ]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        ResearchApplication::factory()->create([
            'application_code' => 'UNLINKED-ADVISER-APP',
            'academic_term_id' => null,
            'adviser_user_id' => $adviser,
            'application_status' => ApplicationStatus::SubmittedToAdviser,
            'submitted_at' => now()->subDay(),
        ]);
        ResearchApplication::factory()->create([
            'application_code' => 'UNLINKED-RES-APP',
            'academic_term_id' => null,
            'application_status' => ApplicationStatus::AdviserEndorsed,
            'submitted_at' => now()->subDay(),
        ]);

        $this->actingAs($adviser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Pending: 0"', false)
            ->assertDontSee('UNLINKED-ADVISER-APP');

        $this->actingAs($resLead)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="For REU Screening: 0"', false)
            ->assertDontSee('UNLINKED-RES-APP');
    }

    public function test_role_dashboards_select_their_configured_process_deadlines(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'application_status' => ApplicationStatus::RevisionWindowOpen,
            'current_stage' => ApplicationStage::Revision,
            'submitted_at' => now()->subWeek(),
        ]);
        DeadlineConfiguration::create([
            'deadline_key' => 'mapped-application-submission',
            'title' => 'Mapped Application Submission',
            'audience_role' => UserRole::Applicant,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDays(4),
            'priority' => 100,
            'is_active' => true,
        ]);
        DeadlineConfiguration::create([
            'deadline_key' => 'mapped-revision-period',
            'title' => 'Mapped Revision Period',
            'audience_role' => UserRole::Applicant,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDays(3),
            'priority' => 100,
            'is_active' => true,
        ]);
        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mapped Revision Period')
            ->assertDontSee('Mapped Application Submission');

        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        DeadlineConfiguration::create([
            'deadline_key' => 'mapped-adviser-endorsement',
            'title' => 'Mapped Endorsement Period',
            'audience_role' => UserRole::Adviser,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDays(2),
            'priority' => 100,
            'is_active' => true,
        ]);
        $this->actingAs($adviser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mapped Endorsement Period');

        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        ReviewerAssignment::factory()->create([
            'reviewer_user_id' => $reviewer,
            'assignment_status' => ReviewerAssignmentStatus::RevisionReview,
        ]);
        DeadlineConfiguration::create([
            'deadline_key' => 'mapped-reviewer-submission',
            'title' => 'Mapped Reviewing Period',
            'audience_role' => UserRole::Reviewer,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDays(3),
            'priority' => 100,
            'is_active' => true,
        ]);
        DeadlineConfiguration::create([
            'deadline_key' => 'mapped-reviewing-revision-period',
            'title' => 'Mapped Reviewing of Revision Period',
            'audience_role' => UserRole::Reviewer,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDays(2),
            'priority' => 100,
            'is_active' => true,
        ]);
        $this->actingAs($reviewer)
            ->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('Mapped Reviewing of Revision Period')
            ->assertDontSee('Mapped Reviewing Period');

        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        DeadlineConfiguration::create([
            'deadline_key' => 'mapped-res-screening',
            'title' => 'Mapped REU Screening',
            'audience_role' => UserRole::ResLead,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDay(),
            'priority' => 100,
            'is_active' => true,
        ]);
        DeadlineConfiguration::create([
            'deadline_key' => 'mapped-result-release',
            'title' => 'Release of Decision & Certificate',
            'audience_role' => UserRole::ResLead,
            'starts_at' => now()->addDays(5),
            'due_at' => now()->addDays(5),
            'priority' => 100,
            'is_active' => true,
        ]);
        $this->actingAs($resLead)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mapped REU Screening')
            ->assertDontSee('Release of Decision &amp; Certificate', false);
    }

    public function test_adviser_dashboard_keeps_archived_applicant_identity_for_historical_applications(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Archived Applicant',
        ]);
        ResearchApplication::factory()->create([
            'application_code' => 'ADV-ARCHIVED-001',
            'applicant_user_id' => $applicant->id,
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::SubmittedToAdviser,
            'submitted_at' => now(),
        ]);
        $applicant->delete();

        $this->actingAs($adviser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ADV-ARCHIVED-001')
            ->assertSee('Archived Applicant');
    }

    public function test_reviewer_dashboard_counts_assignments_and_near_deadline_from_real_records(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $otherReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $application = ResearchApplication::factory()->create([
            'application_code' => 'REV-001',
            'application_status' => ApplicationStatus::UnderExpeditedReview,
            'submitted_at' => now()->subDays(2),
        ]);

        ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::Pending,
            'review_deadline_at' => now()->addDays(2),
        ]);
        ReviewerAssignment::factory()->create([
            'reviewer_user_id' => $reviewer->id,
            'review_type' => 'revision_review',
            'assignment_status' => ReviewerAssignmentStatus::RevisionReview,
            'review_deadline_at' => now()->addDays(8),
        ]);
        $completedApplication = ResearchApplication::factory()->create([
            'application_status' => ApplicationStatus::ForCertificateRelease,
            'submitted_at' => now()->subDays(4),
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $completedApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'review_deadline_at' => now()->subDay(),
            'submitted_at' => now()->subHour(),
        ]);
        ApplicationDecisionRelease::create([
            'research_application_id' => $completedApplication->id,
            'review_cycle' => 0,
            'source_review_type' => 'initial_review',
            'decision' => ReviewDecision::Approved,
            'released_by_user_id' => $resLead->id,
            'released_at' => now()->subMinutes(30),
        ]);
        ReviewerAssignment::factory()->create([
            'reviewer_user_id' => $otherReviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::Pending,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Pending Reviews: 1"', false)
            ->assertSee('aria-label="Revision Reviews: 1"', false)
            ->assertSee('aria-label="Completed Reviews: 1"', false)
            ->assertSee('REV-001');
    }

    public function test_reviewer_dashboard_uses_only_the_latest_cycle_and_does_not_complete_an_open_revision(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $application = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-LATEST-CYCLE-ONLY',
            'application_status' => ApplicationStatus::UnderReReview,
            'current_revision_cycle' => 2,
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'review_cycle' => 0,
            'review_type' => 'initial_review',
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now()->subDays(2),
            'assigned_at' => now()->subDays(4),
        ]);
        $release = ApplicationDecisionRelease::create([
            'research_application_id' => $application->id,
            'review_cycle' => 0,
            'source_review_type' => 'initial_review',
            'decision' => ReviewDecision::MinorRevision,
            'released_by_user_id' => $resLead->id,
            'released_at' => now()->subDay(),
        ]);
        ApplicationRevision::create([
            'research_application_id' => $application->id,
            'application_decision_release_id' => $release->id,
            'revision_number' => 1,
            'status' => ApplicationRevisionStatus::UnderReview,
            'due_at' => now()->addDays(5),
            'submitted_at' => now()->subHour(),
        ]);
        $latestAssignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'review_cycle' => 1,
            'review_type' => 'revision_review',
            'assignment_status' => ReviewerAssignmentStatus::RevisionReview,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($reviewer)->get(route('reviewer.dashboard'));

        $response
            ->assertOk()
            ->assertSee('aria-label="Revision Reviews: 1"', false)
            ->assertSee('aria-label="Completed Reviews: 0"', false)
            ->assertViewHas('assignments', fn ($assignments): bool => $assignments->count() === 1
                && $assignments->first()->is($latestAssignment));
    }

    public function test_reviewer_dashboard_uses_current_fresh_assignments_after_status_reassignment_and_revocation_changes(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $replacementReviewer = User::factory()->create(['role' => UserRole::Reviewer]);

        $statusChangedApplication = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-CURRENT-STATUS',
            'application_status' => ApplicationStatus::UnderExpeditedReview,
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $statusChangedApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::InReview,
            'assigned_at' => now()->subDays(3),
            'updated_at' => now(),
        ]);

        $newApplication = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-NEW-ASSIGNMENT',
            'application_status' => ApplicationStatus::UnderExpeditedReview,
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $newApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::Pending,
            'assigned_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $reassignedApplication = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-REASSIGNED-AWAY',
            'application_status' => ApplicationStatus::UnderExpeditedReview,
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $reassignedApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::Superseded,
            'superseded_at' => now(),
            'updated_at' => now()->addMinute(),
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $reassignedApplication->id,
            'reviewer_user_id' => $replacementReviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::Pending,
        ]);

        $revokedApplication = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-REVOKED',
            'application_status' => ApplicationStatus::UnderExpeditedReview,
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $revokedApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::Superseded,
            'superseded_at' => now(),
        ]);

        $archivedApplication = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-ARCHIVED',
            'application_status' => ApplicationStatus::Archived,
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $archivedApplication->id,
            'reviewer_user_id' => $reviewer->id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee('Latest Assigned Reviews')
            ->assertSeeInOrder(['REVIEW-CURRENT-STATUS', 'REVIEW-NEW-ASSIGNMENT'])
            ->assertSee('In Review')
            ->assertDontSee('REVIEW-REASSIGNED-AWAY')
            ->assertDontSee('REVIEW-REVOKED')
            ->assertDontSee('REVIEW-ARCHIVED');
    }

    public function test_res_dashboard_counts_each_administrative_queue_from_application_statuses(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $statuses = [
            'RES-001' => ApplicationStatus::AdviserEndorsed,
            'RES-002' => ApplicationStatus::UnderResScreening,
            'RES-003' => ApplicationStatus::AwaitingReviewerAssignment,
            'RES-004' => ApplicationStatus::UnderFullBoardReview,
            'RES-005' => ApplicationStatus::ReviewSubmittedPendingRelease,
        ];

        foreach ($statuses as $code => $status) {
            ResearchApplication::factory()->create([
                'application_code' => $code,
                'application_status' => $status,
                'submitted_at' => now(),
            ]);
        }

        $this->actingAs($resLead)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="For REU Screening: 1"', false)
            ->assertSee('aria-label="Under REU Screening: 1"', false)
            ->assertSee('aria-label="Awaiting Assignment: 1"', false)
            ->assertSee('aria-label="Under Review: 1"', false)
            ->assertSee('aria-label="For Result Release: 1"', false)
            ->assertSee('RES-001')
            ->assertSee('RES-005');
    }

    public function test_res_dashboard_displays_eager_loaded_advisers_and_an_exact_unassigned_fallback(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $adviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Dashboard Ethics Adviser',
        ]);
        ResearchApplication::factory()->create([
            'application_code' => 'RES-WITH-ADVISER',
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::AdviserEndorsed,
            'submitted_at' => now()->subMinute(),
        ]);
        ResearchApplication::factory()->create([
            'application_code' => 'RES-WITHOUT-ADVISER',
            'adviser_user_id' => null,
            'application_status' => ApplicationStatus::UnderResScreening,
            'submitted_at' => now(),
        ]);

        // Relation-loaded assertions guard the five-row dashboard against per-row Adviser queries.
        $applications = app(DashboardDataService::class)->resLead()['applications'];
        $this->assertTrue($applications->every(
            fn (ResearchApplication $application): bool => $application->relationLoaded('adviser'),
        ));

        $this->actingAs($resLead)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<th>Adviser</th>', false)
            ->assertSee('Dashboard Ethics Adviser')
            ->assertSee('Not assigned')
            ->assertDontSee('<th>Applicant Category</th>', false);
    }

    /**
     * Verify every role that uses shared summary cards renders the icon before right-side zero-count copy.
     */
    public function test_shared_summary_cards_render_icon_count_and_label_order_for_zero_counts(): void
    {
        // Arrange the role-specific labels expected when the database contains no applications or assignments.
        $cases = [
            UserRole::Adviser->value => ['Pending', 'Endorsed', 'Returned'],
            UserRole::ResLead->value => ['For REU Screening', 'Under REU Screening', 'Awaiting Assignment', 'Under Review', 'For Result Release'],
        ];

        foreach ($cases as $role => $labels) {
            // Act as a fresh role account so every shared card receives a database-derived zero count.
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('dashboard'));

            // Assert the reusable component keeps icon, count, then label order and exposes each count-label pair accessibly.
            $response->assertOk()
                ->assertSeeInOrder([
                    'dashboard-summary-icon',
                    'dashboard-summary-copy',
                    '<strong>0</strong>',
                    '<span>'.$labels[0].'</span>',
                ], false);

            foreach ($labels as $label) {
                $response->assertSee('aria-label="'.$label.': 0"', false);
            }
        }

        $reviewer = User::factory()->reviewer()->create();
        $reviewerResponse = $this->actingAs($reviewer)->get(route('reviewer.dashboard'));
        $reviewerResponse->assertOk()->assertSeeInOrder([
            'dashboard-summary-icon',
            'dashboard-summary-copy',
            '<strong>0</strong>',
            '<span>Pending Reviews</span>',
        ], false);
        foreach (['Pending Reviews', 'Revision Reviews', 'Completed Reviews'] as $label) {
            $reviewerResponse->assertSee('aria-label="'.$label.': 0"', false);
        }
    }

    public function test_shared_layout_css_keeps_cards_overflow_and_form_spacing_within_approved_structure(): void
    {
        // Arrange the compiled-source stylesheet text used by every dashboard role.
        $css = (string) file_get_contents(resource_path('css/dashboard.css'));

        // Assert summary cards use a fixed left icon column and a centered stacked right copy group.
        $this->assertMatchesRegularExpression(
            '/\.dashboard-summary-card\s*\{[^}]*grid-template-columns:\s*58px minmax\(0,\s*1fr\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.dashboard-summary-copy\s*\{[^}]*display:\s*grid;[^}]*justify-items:\s*center;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.dashboard-summary-copy span\s*\{[^}]*text-align:\s*center;/s',
            $css,
        );

        // Assert wide content scrolls internally while the application workspace itself stays width-bounded.
        $this->assertMatchesRegularExpression(
            '/\.dashboard-overflow-region\s*\{[^}]*overflow-x:\s*auto;/s',
            $css,
        );
        preg_match('/\.application-workspace\s*\{(?<rules>[^}]*)\}/s', $css, $workspace);
        $this->assertArrayHasKey('rules', $workspace);
        $this->assertStringContainsString('max-width: 100%', $workspace['rules']);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr)', $workspace['rules']);
        $this->assertStringNotContainsString('overflow-x', $workspace['rules']);

        // Assert shared account-section headings retain compact ten-pixel spacing before their first row.
        $this->assertMatchesRegularExpression(
            '/\.identity-form-section-title,\s*\.identity-form-section legend\s*\{[^}]*margin-bottom:\s*10px;/s',
            $css,
        );
    }

    public function test_every_blade_table_uses_the_shared_horizontal_overflow_boundary(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = $file->getContents();
            $tableCount = preg_match_all('/<table\b/i', $contents);

            if ($tableCount === 0) {
                continue;
            }

            $componentWrapperCount = preg_match_all('/<x-dashboard\.overflow\b/i', $contents);
            $classWrapperCount = preg_match_all(
                '/class=(["\'])[^"\']*\bdashboard-overflow-region\b[^"\']*\1/i',
                $contents,
            );

            $this->assertGreaterThanOrEqual(
                $tableCount,
                $componentWrapperCount + $classWrapperCount,
                "Every table in {$file->getRelativePathname()} must have its own shared overflow boundary.",
            );
        }
    }

    public function test_shared_browser_identity_and_profile_menu_are_kept_minimal(): void
    {
        $topbar = File::get(resource_path('views/components/dashboard/topbar.blade.php'));
        $dashboardLayout = File::get(resource_path('views/layouts/dashboard.blade.php'));
        $login = File::get(resource_path('views/auth/login.blade.php'));

        $this->assertStringContainsString('<span>Settings</span>', $topbar);
        $this->assertStringContainsString('<span>Logout</span>', $topbar);
        $this->assertStringNotContainsString('<span>Profile</span>', $topbar);
        $this->assertStringContainsString('rel="icon"', $dashboardLayout);
        $this->assertStringContainsString("Vite::asset('assets/logo-256.png')", $dashboardLayout);
        $this->assertStringContainsString('rel="icon"', $login);
    }

    public function test_dashboard_term_query_is_ignored_and_every_role_remains_current_term_and_owner_scoped(): void
    {
        $historicalTerm = AcademicTerm::create([
            'semester' => 'Historical Dashboard Term',
            'academic_year' => '2025-2026',
            'starts_at' => now()->subYear(),
            'ends_at' => now()->subMonths(7),
            'is_active' => false,
        ]);
        $currentTerm = AcademicTerm::create([
            'semester' => 'Current Dashboard Term',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(4),
            'is_active' => true,
        ]);

        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        ResearchApplication::factory()->create([
            'application_code' => 'APPLICANT-HISTORICAL-TERM',
            'applicant_user_id' => $applicant->id,
            'academic_term_id' => $historicalTerm->id,
            'created_at' => now()->subMonth(),
        ]);
        ResearchApplication::factory()->create([
            'application_code' => 'APPLICANT-CURRENT-TERM',
            'applicant_user_id' => $applicant->id,
            'academic_term_id' => $currentTerm->id,
            'created_at' => now(),
        ]);
        $this->actingAs($applicant)
            ->get(route('dashboard', ['academic_term_id' => $historicalTerm->id]))
            ->assertOk()
            ->assertSee('APPLICANT-CURRENT-TERM')
            ->assertDontSee('APPLICANT-HISTORICAL-TERM');

        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $otherAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        foreach ([
            ['ADVISER-HISTORICAL-TERM', $adviser, $historicalTerm],
            ['ADVISER-CURRENT-TERM', $adviser, $currentTerm],
            ['ADVISER-FOREIGN-RECORD', $otherAdviser, $historicalTerm],
        ] as [$code, $owner, $term]) {
            ResearchApplication::factory()->create([
                'application_code' => $code,
                'adviser_user_id' => $owner->id,
                'academic_term_id' => $term->id,
                'application_status' => ApplicationStatus::SubmittedToAdviser,
                'submitted_at' => now(),
            ]);
        }
        $this->actingAs($adviser)
            ->get(route('dashboard', ['academic_term_id' => $historicalTerm->id]))
            ->assertOk()
            ->assertSee('ADVISER-CURRENT-TERM')
            ->assertDontSee('ADVISER-HISTORICAL-TERM')
            ->assertDontSee('ADVISER-FOREIGN-RECORD');

        $reviewer = User::factory()->reviewer()->create();
        $otherReviewer = User::factory()->reviewer()->create();
        foreach ([
            ['REVIEWER-HISTORICAL-TERM', $reviewer, $historicalTerm],
            ['REVIEWER-CURRENT-TERM', $reviewer, $currentTerm],
            ['REVIEWER-FOREIGN-RECORD', $otherReviewer, $historicalTerm],
        ] as [$code, $owner, $term]) {
            $application = ResearchApplication::factory()->create([
                'application_code' => $code,
                'academic_term_id' => $term->id,
                'application_status' => ApplicationStatus::UnderExpeditedReview,
                'submitted_at' => now(),
            ]);
            ReviewerAssignment::factory()->create([
                'research_application_id' => $application->id,
                'reviewer_user_id' => $owner->id,
                'assignment_status' => ReviewerAssignmentStatus::Pending,
            ]);
        }
        $this->actingAs($reviewer)
            ->get(route('reviewer.dashboard', ['academic_term_id' => $historicalTerm->id]))
            ->assertOk()
            ->assertSee('REVIEWER-CURRENT-TERM')
            ->assertDontSee('REVIEWER-HISTORICAL-TERM')
            ->assertDontSee('REVIEWER-FOREIGN-RECORD');

        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        foreach ([
            ['RES-HISTORICAL-TERM', $historicalTerm],
            ['RES-CURRENT-TERM', $currentTerm],
        ] as [$code, $term]) {
            ResearchApplication::factory()->create([
                'application_code' => $code,
                'academic_term_id' => $term->id,
                'application_status' => ApplicationStatus::AdviserEndorsed,
                'submitted_at' => now(),
            ]);
        }
        $this->actingAs($resLead)
            ->get(route('dashboard', ['academic_term_id' => $historicalTerm->id]))
            ->assertOk()
            ->assertSee('RES-CURRENT-TERM')
            ->assertDontSee('RES-HISTORICAL-TERM');

        $this->actingAs($resLead)
            ->get(route('dashboard', ['academic_term_id' => 999999]))
            ->assertOk()
            ->assertSee('RES-CURRENT-TERM')
            ->assertDontSee('RES-HISTORICAL-TERM');
    }

    public function test_role_dashboards_keep_database_query_counts_bounded(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($user)->get(route('dashboard'))->assertOk();

            $queryCount = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertLessThanOrEqual(
                10,
                $queryCount,
                "The {$role->value} dashboard executed {$queryCount} database queries.",
            );
        }
    }

    public function test_dashboard_demo_seeder_is_local_only_data_and_is_idempotent(): void
    {
        $this->seed(DashboardDemoSeeder::class);
        $this->seed(DashboardDemoSeeder::class);

        $this->assertSame(11, ResearchApplication::where('application_code', 'like', 'ECRATS-DEMO-%')->count());
        $this->assertSame(4, DocumentRequirement::count());
        $this->assertSame(4, ReviewerAssignment::count());
        $this->assertSame(7, ApplicationScreening::count());
        $this->assertSame(9, Endorsement::query()->whereNotNull('endorsed_at')->count());
        $this->assertSame(4, DeadlineConfiguration::where('deadline_key', 'like', 'demo-%')->count());
        $this->assertSame(5, TimelineCalendarEvent::where('milestone_key', 'like', 'demo-%')->count());
        $this->assertSame(4, DB::table('notifications')->count());
    }

    private function applicationForAdviser(User $adviser, string $code, ApplicationStatus $status): ResearchApplication
    {
        return ResearchApplication::factory()->create([
            'application_code' => $code,
            'adviser_user_id' => $adviser->id,
            'application_status' => $status,
            'submitted_at' => now(),
        ]);
    }
}
