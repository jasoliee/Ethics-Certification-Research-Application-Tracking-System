<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\RequirementStatus;
use App\Enums\ReviewCommentCategory;
use App\Enums\ReviewCommentScope;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\ApplicantSurveyResponse;
use App\Models\ApplicationDecisionRelease;
use App\Models\ApplicationDocument;
use App\Models\ApplicationRevision;
use App\Models\ApplicationRevisionRequirement;
use App\Models\Certificate;
use App\Models\CertificateVersion;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Support\ApplicantSurveyCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantRevisionPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_revision_uses_closed_requirement_feedback_and_version_accordions_without_certification(): void
    {
        [$applicant, $application, $requirement, $reviewer, $assignment, $release, $first, $second] = $this->releasedApplication(
            ApplicationStatus::RevisionWindowOpen,
            ReviewDecision::MinorRevision,
        );
        $revision = ApplicationRevision::create([
            'research_application_id' => $application->id,
            'application_decision_release_id' => $release->id,
            'revision_number' => 1,
            'status' => ApplicationRevisionStatus::PendingUploads,
            'due_at' => now()->addWeek(),
        ]);
        ApplicationRevisionRequirement::create([
            'application_revision_id' => $revision->id,
            'document_requirement_id' => $requirement->id,
            'source_application_document_id' => $first->id,
            'replacement_application_document_id' => null,
            'is_required' => true,
        ]);
        $this->releaseComment($assignment, $release, $first, 'Revise the participant safeguards in this protocol.');
        $this->releaseComment($assignment, $release, null, 'Apply the released guidance across the whole study.');

        $response = $this->actingAs($applicant)
            ->get(route('applicant.revision-certificates.index', ['application' => $application->id]))
            ->assertOk()
            ->assertSee('is-revision-active', false)
            ->assertSee('data-revision-requirement="requirement-'.$requirement->id.'"', false)
            ->assertSee('data-revision-requirement="overall-application"', false)
            ->assertSee('data-revision-version-select', false)
            ->assertSee('data-revision-version-panel="'.$second->id.'"', false)
            ->assertSee('data-revision-version-panel="'.$first->id.'"', false)
            ->assertSee('Version 2')
            ->assertSee('Version 1')
            ->assertSee('For Revision C1')
            ->assertSee('Reviewer 1')
            ->assertSee('Revise the participant safeguards in this protocol.')
            ->assertSee('Apply the released guidance across the whole study.')
            ->assertDontSee($reviewer->name)
            ->assertSee('id="revision-documents-title"', false)
            ->assertDontSee('id="certification-state-title"', false);

        $this->assertDoesNotMatchRegularExpression(
            '/<details[^>]*revision-requirement-disclosure[^>]*\sopen(?:\s|>)/',
            $response->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/data-revision-version-panel="'.preg_quote((string) $first->id, '/').'"[^>]*\shidden(?:\s|>)/',
            $response->getContent(),
        );

        $application->update(['current_revision_cycle' => 3]);
        $this->assertSame('For Revision C2', $application->refresh()->statusLabel());
    }

    public function test_final_approval_shows_released_feedback_before_pending_certificate_and_hides_revision_submission(): void
    {
        [$applicant, $application, , $reviewer, $assignment, $release, $first] = $this->releasedApplication(
            ApplicationStatus::ForCertificateRelease,
            ReviewDecision::Approved,
        );
        $this->releaseComment($assignment, $release, $first, 'The approved safeguards are recorded for the Applicant.');
        Certificate::create([
            'research_application_id' => $application->id,
            'applicant_user_id' => $applicant->id,
            'certificate_number' => $application->application_code,
            'status' => CertificateStatus::PendingRelease,
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.revision-certificates.index', ['application' => $application->id]))
            ->assertOk()
            ->assertSee('is-final-approved', false)
            ->assertSee('The approved safeguards are recorded for the Applicant.')
            ->assertSee('Reviewer 1')
            ->assertDontSee($reviewer->name)
            ->assertDontSee('id="revision-documents-title"', false)
            ->assertSeeInOrder(['id="released-feedback-title"', 'id="certification-state-title"'], false)
            ->assertSee('Pending Certificate Release')
            ->assertDontSee('Claim Certificate')
            ->assertDontSee('View Certificate')
            ->assertDontSee('Download Certificate');
    }

    public function test_released_certificate_keeps_anonymous_feedback_before_the_claim_action(): void
    {
        [$applicant, $application, , $reviewer, $assignment, $release, $first] = $this->releasedApplication(
            ApplicationStatus::CertificateReleased,
            ReviewDecision::Approved,
        );
        $this->releaseComment($assignment, $release, $first, 'Approved feedback remains available before certificate claim.');
        $certificate = Certificate::create([
            'research_application_id' => $application->id,
            'applicant_user_id' => $applicant->id,
            'certificate_number' => $application->application_code,
            'status' => CertificateStatus::Released,
            'released_by_user_id' => $release->released_by_user_id,
            'released_at' => now()->subHour(),
        ]);
        $version = CertificateVersion::create([
            'certificate_id' => $certificate->id,
            'certificate_version' => 1,
            'status' => CertificateVersionStatus::Ready,
            'stored_file_path' => "certificates/{$certificate->id}/certificate.pdf",
            'original_file_name' => 'ethics-certificate.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 256,
            'sha256' => hash('sha256', 'certificate'),
            'official_template_version' => 'test-v1',
            'official_template_sha256' => hash('sha256', 'template'),
            'background_sha256' => hash('sha256', 'background'),
            'generator_version' => 'test-generator',
            'generated_by_user_id' => $release->released_by_user_id,
            'generated_at' => now()->subHour(),
            'released_by_user_id' => $release->released_by_user_id,
            'released_at' => now()->subHour(),
        ]);
        $certificate->update(['current_certificate_version_id' => $version->id]);
        ApplicantSurveyResponse::create([
            'research_application_id' => $application->id,
            'applicant_user_id' => $applicant->id,
            'questionnaire_version' => ApplicantSurveyCatalog::VERSION,
            'ratings' => array_fill_keys(ApplicantSurveyCatalog::questionKeys(), 5),
            'positive_feedback' => 'Legacy compatibility value.',
            'improvement_feedback' => 'Legacy compatibility value.',
            'suggestions_comments' => null,
            'completed_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.revision-certificates.index', ['application' => $application->id]))
            ->assertOk()
            ->assertSee('Approved feedback remains available before certificate claim.')
            ->assertSee('Reviewer 1')
            ->assertDontSee($reviewer->name)
            ->assertDontSee('id="revision-documents-title"', false)
            ->assertSeeInOrder(['id="released-feedback-title"', 'Claim Certificate'], false)
            ->assertDontSee('View Certificate')
            ->assertDontSee('Download Certificate');
    }

    /**
     * @return array{User, ResearchApplication, DocumentRequirement, User, ReviewerAssignment, ApplicationDecisionRelease, ApplicationDocument, ApplicationDocument}
     */
    private function releasedApplication(ApplicationStatus $status, ReviewDecision $decision): array
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $reviewer = User::factory()->reviewer()->create(['name' => 'Private Reviewer Identity']);
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $application = ResearchApplication::factory()->create([
            'application_code' => 'RES-2026-S-ICDI-08172026-UI0001',
            'applicant_user_id' => $applicant->id,
            'application_status' => $status,
            'current_stage' => match ($status) {
                ApplicationStatus::RevisionWindowOpen => ApplicationStage::Revision,
                ApplicationStatus::CertificateReleased => ApplicationStage::Completed,
                default => ApplicationStage::DecisionRelease,
            },
            'current_revision_cycle' => $status === ApplicationStatus::RevisionWindowOpen ? 2 : 1,
            'submitted_at' => now()->subWeek(),
        ]);
        $requirement = DocumentRequirement::create([
            'code' => 'REVISION-UI-PROTOCOL',
            'name' => 'Research Protocol',
            'is_mandatory' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $first = $this->document($application, $requirement, $applicant, 1, false, 'protocol-v1.pdf');
        $second = $this->document($application, $requirement, $applicant, 2, true, 'protocol-v2.pdf');
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => 'initial_review',
            'review_cycle' => 0,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now()->subDays(2),
        ]);
        $release = ApplicationDecisionRelease::create([
            'research_application_id' => $application->id,
            'review_cycle' => 0,
            'source_review_type' => 'initial_review',
            'decision' => $decision,
            'released_by_user_id' => $resLead->id,
            'released_at' => now()->subDay(),
        ]);

        return [$applicant, $application, $requirement, $reviewer, $assignment, $release, $first, $second];
    }

    private function document(
        ResearchApplication $application,
        DocumentRequirement $requirement,
        User $applicant,
        int $version,
        bool $current,
        string $name,
    ): ApplicationDocument {
        return ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => $name,
            'stored_file_path' => "applications/{$application->id}/{$name}",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 128,
            'file_sha256' => hash('sha256', $name),
            'document_version' => $version,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => $current,
            'uploaded_at' => now()->subDays(3 - $version),
        ]);
    }

    private function releaseComment(
        ReviewerAssignment $assignment,
        ApplicationDecisionRelease $release,
        ?ApplicationDocument $document,
        string $body,
    ): ReviewComment {
        return ReviewComment::create([
            'reviewer_assignment_id' => $assignment->id,
            'application_document_id' => $document?->id,
            'scope' => $document ? ReviewCommentScope::Document : ReviewCommentScope::Overall,
            'category' => $document ? ReviewCommentCategory::RequiredRevision : ReviewCommentCategory::General,
            'body' => $body,
            'application_decision_release_id' => $release->id,
            'released_at' => now()->subDay(),
            'released_by_user_id' => $release->released_by_user_id,
        ]);
    }
}
