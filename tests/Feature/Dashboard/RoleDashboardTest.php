<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\TimelineCalendarEvent;
use App\Models\User;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_dashboard_displays_its_database_driven_empty_state(): void
    {
        $cases = [
            UserRole::Applicant->value => 'No application yet',
            UserRole::Adviser->value => 'No submitted applications yet',
            UserRole::Reviewer->value => 'No assigned applications yet',
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
    }

    public function test_applicant_dashboard_displays_active_application_requirements_deadline_and_milestone(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'application_code' => 'ECRATS-TEST-0001',
            'applicant_user_id' => $applicant->id,
            'adviser_user_id' => $adviser->id,
            'research_title' => 'Ethical Use of Learning Analytics',
            'application_status' => ApplicationStatus::UnderResScreening,
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
            'deadline_key' => 'applicant-test-deadline',
            'title' => 'Application submission deadline',
            'audience_role' => UserRole::Applicant,
            'due_at' => now()->addDays(2),
            'priority' => 5,
            'is_active' => true,
        ]);
        TimelineCalendarEvent::create([
            'milestone_key' => 'test-screening',
            'label' => 'RES Screening',
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
            ->assertSee('Under RES Screening')
            ->assertSee('1 of 2 completed')
            ->assertSee('Application submission deadline')
            ->assertSee('1st Semester, A.Y. 2026-2027')
            ->assertSee('dashboard-panel-header-meta', false)
            ->assertSee('data-research-title-tooltip', false)
            ->assertDontSee('No application yet');
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
            ->assertSee('aria-label="In Review: 1"', false)
            ->assertSee('aria-label="Endorsed: 1"', false)
            ->assertSee('aria-label="Returned: 1"', false)
            ->assertSee('ADV-001')
            ->assertDontSee('OTHER-001');
    }

    public function test_reviewer_dashboard_counts_assignments_and_near_deadline_from_real_records(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $otherReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
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
        ReviewerAssignment::factory()->create([
            'reviewer_user_id' => $reviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'review_deadline_at' => now()->subDay(),
            'submitted_at' => now()->subHour(),
        ]);
        ReviewerAssignment::factory()->create([
            'reviewer_user_id' => $otherReviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::Pending,
        ]);

        $this->actingAs($reviewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Pending Reviews: 1"', false)
            ->assertSee('aria-label="Near Deadline: 1"', false)
            ->assertSee('aria-label="Revision Reviews: 1"', false)
            ->assertSee('aria-label="Completed Reviews: 1"', false)
            ->assertSee('REV-001');
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
            ->assertSee('aria-label="For RES Screening: 1"', false)
            ->assertSee('aria-label="Under RES Screening: 1"', false)
            ->assertSee('aria-label="Awaiting Assignment: 1"', false)
            ->assertSee('aria-label="Under Review: 1"', false)
            ->assertSee('aria-label="For Result Release: 1"', false)
            ->assertSee('RES-001')
            ->assertSee('RES-005');
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
                8,
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
        $this->assertSame(5, DeadlineConfiguration::where('deadline_key', 'like', 'demo-%')->count());
        $this->assertSame(6, TimelineCalendarEvent::where('milestone_key', 'like', 'demo-%')->count());
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
