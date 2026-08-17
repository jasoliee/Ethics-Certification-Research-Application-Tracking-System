<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDecisionRelease;
use App\Models\ApplicationDocument;
use App\Models\ApplicationRevision;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewerAssignmentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_index_lists_only_the_authenticated_reviewers_records_and_filters_them(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $otherReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $pendingApplication = ResearchApplication::factory()->create([
            'application_code' => 'RES-REVIEW-OWN-001',
            'research_title' => 'Accessible Water Quality Review',
        ]);
        $completedApplication = ResearchApplication::factory()->create([
            'application_code' => 'RES-REVIEW-OWN-002',
            'research_title' => 'Completed Data Governance Review',
        ]);
        $otherApplication = ResearchApplication::factory()->create([
            'application_code' => 'RES-REVIEW-OTHER-001',
            'research_title' => 'Private Assignment for Another Reviewer',
        ]);

        ReviewerAssignment::factory()->create([
            'research_application_id' => $pendingApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => 'initial_review',
            'assignment_status' => ReviewerAssignmentStatus::Pending,
            'review_deadline_at' => now()->addDays(3),
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $completedApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => 'revision_review',
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'review_deadline_at' => now()->subDay(),
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $otherApplication->id,
            'reviewer_user_id' => $otherReviewer->id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.index'))
            ->assertOk()
            ->assertSee('Assigned Applications')
            ->assertSee($pendingApplication->application_code)
            ->assertSee($completedApplication->application_code)
            ->assertDontSee($otherApplication->application_code)
            ->assertSeeInOrder(['Application Code', 'Research Title', 'Review Type', 'Status', 'Deadline', 'Action']);

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.index', [
                'review_cycle' => 'revision_review',
                'status' => ReviewerAssignmentStatus::DecisionSubmitted->value,
                'q' => 'Data Governance',
            ]))
            ->assertOk()
            ->assertSee($completedApplication->application_code)
            ->assertDontSee($pendingApplication->application_code)
            ->assertDontSee($otherApplication->application_code);
    }

    public function test_assignment_index_paginates_owned_records(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);

        foreach (range(1, 16) as $index) {
            $application = ResearchApplication::factory()->create([
                'application_code' => sprintf('RES-PAGE-%03d', $index),
            ]);
            ReviewerAssignment::factory()->create([
                'research_application_id' => $application->id,
                'reviewer_user_id' => $reviewer->id,
                'assigned_at' => now()->subMinutes($index),
            ]);
        }

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.index'))
            ->assertOk()
            ->assertSee('aria-label="Assigned application pages"', false)
            ->assertSee('RES-PAGE-001')
            ->assertDontSee('RES-PAGE-016');

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('RES-PAGE-016');
    }

    public function test_review_task_tabs_separate_revisions_and_only_complete_the_latest_final_approval(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $revisionApplication = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-TAB-ACTIVE-REVISION',
            'application_status' => ApplicationStatus::UnderReReview,
            'current_revision_cycle' => 2,
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $revisionApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'review_cycle' => 0,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now()->subDays(2),
        ]);
        $revisionRelease = ApplicationDecisionRelease::create([
            'research_application_id' => $revisionApplication->id,
            'review_cycle' => 0,
            'source_review_type' => 'initial_review',
            'decision' => ReviewDecision::MinorRevision,
            'released_by_user_id' => $resLead->id,
            'released_at' => now()->subDay(),
        ]);
        ApplicationRevision::create([
            'research_application_id' => $revisionApplication->id,
            'application_decision_release_id' => $revisionRelease->id,
            'revision_number' => 1,
            'status' => ApplicationRevisionStatus::UnderReview,
            'due_at' => now()->addDays(5),
            'submitted_at' => now()->subHour(),
        ]);
        $revisionAssignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $revisionApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'review_cycle' => 1,
            'review_type' => 'revision_review',
            'assignment_status' => ReviewerAssignmentStatus::RevisionReview,
            'assigned_at' => now(),
        ]);

        $completedApplication = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-TAB-FINAL-APPROVAL',
            'application_status' => ApplicationStatus::ForCertificateRelease,
        ]);
        $completedAssignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $completedApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'review_cycle' => 0,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now()->subHour(),
        ]);
        ApplicationDecisionRelease::create([
            'research_application_id' => $completedApplication->id,
            'review_cycle' => 0,
            'source_review_type' => 'initial_review',
            'decision' => ReviewDecision::Approved,
            'released_by_user_id' => $resLead->id,
            'released_at' => now(),
        ]);

        $openRevisionApplication = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-TAB-OPEN-REVISION',
            'application_status' => ApplicationStatus::RevisionWindowOpen,
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $openRevisionApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now(),
        ]);
        $openRevisionRelease = ApplicationDecisionRelease::create([
            'research_application_id' => $openRevisionApplication->id,
            'review_cycle' => 0,
            'source_review_type' => 'initial_review',
            'decision' => ReviewDecision::Approved,
            'released_by_user_id' => $resLead->id,
            'released_at' => now(),
        ]);
        ApplicationRevision::create([
            'research_application_id' => $openRevisionApplication->id,
            'application_decision_release_id' => $openRevisionRelease->id,
            'revision_number' => 1,
            'status' => ApplicationRevisionStatus::PendingUploads,
            'due_at' => now()->addDays(7),
        ]);

        $unreleasedApplication = ResearchApplication::factory()->create([
            'application_code' => 'REVIEW-TAB-NOT-RELEASED',
            'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease,
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $unreleasedApplication->id,
            'reviewer_user_id' => $reviewer->id,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.index'))
            ->assertOk()
            ->assertViewHas('assignments', function ($assignments) use ($revisionApplication, $revisionAssignment): bool {
                $lineage = $assignments->getCollection()
                    ->where('research_application_id', $revisionApplication->id);

                return $lineage->count() === 1 && $lineage->first()->is($revisionAssignment);
            });

        $this->actingAs($reviewer)
            ->get(route('reviewer.reviews.index'))
            ->assertOk()
            ->assertViewHas('assignments', fn ($assignments): bool => $assignments->isEmpty());

        $this->actingAs($reviewer)
            ->get(route('reviewer.reviews.index', ['tab' => 'revision']))
            ->assertOk()
            ->assertSee('reviewer-task-tabs', false)
            ->assertSee('Revision Reviews')
            ->assertSee($revisionApplication->application_code)
            ->assertDontSee($completedApplication->application_code)
            ->assertViewHas('assignments', fn ($assignments): bool => $assignments->count() === 1
                && $assignments->first()->is($revisionAssignment));

        $this->actingAs($reviewer)
            ->get(route('reviewer.reviews.index', ['tab' => 'completed']))
            ->assertOk()
            ->assertSee('Completed Reviews')
            ->assertSeeInOrder(['tone-success', 'Completed'], false)
            ->assertSee($completedApplication->application_code)
            ->assertDontSee($revisionApplication->application_code)
            ->assertDontSee($openRevisionApplication->application_code)
            ->assertDontSee($unreleasedApplication->application_code)
            ->assertViewHas('assignments', fn ($assignments): bool => $assignments->count() === 1
                && $assignments->first()->is($completedAssignment));
    }

    public function test_assignment_detail_and_documents_require_the_exact_reviewer_assignment(): void
    {
        Storage::fake('local');
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $otherReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Applicant Identity Must Stay Hidden',
        ]);
        $adviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Adviser Identity Must Stay Hidden',
        ]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant->id,
            'adviser_user_id' => $adviser->id,
            'application_code' => 'RES-PRIVATE-REVIEW-001',
            'research_title' => 'Privacy-Preserving Review Workflow',
        ]);
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
        ]);
        $requirement = DocumentRequirement::create([
            'code' => 'REVIEWER-PROPOSAL',
            'name' => 'Research Proposal',
            'is_mandatory' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $document = ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => 'review-copy.pdf',
            'stored_file_path' => "applications/private/{$application->id}/review-copy.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now(),
        ]);
        Storage::disk('local')->put($document->stored_file_path, '%PDF-1.4 private');

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.show', $assignment))
            ->assertOk()
            ->assertSee($application->application_code)
            ->assertSee($application->research_title)
            ->assertSee($document->original_file_name)
            ->assertSee(route('reviewer.applications.documents.preview', [$application, $document]), false)
            ->assertSee(route('reviewer.applications.documents.download', [$application, $document]), false)
            ->assertSee('reviewer-study-overview', false)
            ->assertSee('reviewer-document-primary-column', false)
            ->assertDontSee($applicant->name)
            ->assertDontSee($adviser->name);

        $preview = $this->actingAs($reviewer)
            ->get(route('reviewer.applications.documents.preview', [$application, $document]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));

        $this->actingAs($otherReviewer)
            ->get(route('reviewer.assignments.show', $assignment))
            ->assertForbidden();
        $this->actingAs($otherReviewer)
            ->get(route('reviewer.applications.documents.download', [$application, $document]))
            ->assertForbidden();
    }

    public function test_assignment_surfaces_define_bounded_table_and_filter_breakpoints(): void
    {
        $css = file_get_contents(resource_path('css/dashboard.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.reviewer-assignment-page,\s*\.reviewer-assignment-detail-page\s*\{[^}]*min-width:\s*0;[^}]*max-width:\s*100%;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@container reviewer-assignment-workspace \(max-width:\s*1120px\).*?grid-template-columns:\s*repeat\(3,\s*minmax\(0,\s*1fr\)\)/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@container reviewer-assignment-workspace \(max-width:\s*560px\).*?\.reviewer-assignment-filter-bar,\s*\.reviewer-assignment-details\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.res-reviewer-filter-bar\s*>\s*\*[^}]*min-width:\s*0;[^}]*max-width:\s*100%;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.reviewer-document-table \.reviewer-document-primary-column\s*\{[^}]*text-align:\s*left;/s',
            $css,
        );
    }
}
