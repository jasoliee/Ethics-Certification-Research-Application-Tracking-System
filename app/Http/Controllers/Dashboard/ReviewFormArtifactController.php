<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\ReviewFormArtifact;
use App\Models\ReviewFormSubmission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams immutable official review PDFs without disclosing private disk paths.
 */
class ReviewFormArtifactController extends Controller
{
    public function reviewerPreview(
        ReviewerAssignment $reviewerAssignment,
        ReviewFormSubmission $reviewFormSubmission,
        ReviewFormArtifact $reviewFormArtifact,
    ): StreamedResponse {
        $this->assertReviewerNesting($reviewerAssignment, $reviewFormSubmission, $reviewFormArtifact);
        Gate::authorize('view', $reviewFormArtifact);

        return $this->fileResponse($reviewFormArtifact, 'inline');
    }

    public function reviewerDownload(
        ReviewerAssignment $reviewerAssignment,
        ReviewFormSubmission $reviewFormSubmission,
        ReviewFormArtifact $reviewFormArtifact,
    ): StreamedResponse {
        $this->assertReviewerNesting($reviewerAssignment, $reviewFormSubmission, $reviewFormArtifact);
        Gate::authorize('view', $reviewFormArtifact);

        return $this->fileResponse($reviewFormArtifact, 'attachment');
    }

    public function resPreview(
        ResearchApplication $researchApplication,
        ReviewerAssignment $reviewerAssignment,
        ReviewFormSubmission $reviewFormSubmission,
        ReviewFormArtifact $reviewFormArtifact,
    ): StreamedResponse {
        $this->assertResNesting($researchApplication, $reviewerAssignment, $reviewFormSubmission, $reviewFormArtifact);
        Gate::authorize('view', $reviewFormArtifact);

        return $this->fileResponse($reviewFormArtifact, 'inline');
    }

    public function resDownload(
        ResearchApplication $researchApplication,
        ReviewerAssignment $reviewerAssignment,
        ReviewFormSubmission $reviewFormSubmission,
        ReviewFormArtifact $reviewFormArtifact,
    ): StreamedResponse {
        $this->assertResNesting($researchApplication, $reviewerAssignment, $reviewFormSubmission, $reviewFormArtifact);
        Gate::authorize('view', $reviewFormArtifact);

        return $this->fileResponse($reviewFormArtifact, 'attachment');
    }

    private function assertReviewerNesting(
        ReviewerAssignment $assignment,
        ReviewFormSubmission $form,
        ReviewFormArtifact $artifact,
    ): void {
        abort_unless(
            $form->reviewer_assignment_id === $assignment->id
                && $artifact->review_form_submission_id === $form->id,
            404,
        );
        $artifact->loadMissing('formSubmission.assignment.reviewSubmission');
    }

    private function assertResNesting(
        ResearchApplication $application,
        ReviewerAssignment $assignment,
        ReviewFormSubmission $form,
        ReviewFormArtifact $artifact,
    ): void {
        abort_unless($assignment->research_application_id === $application->id, 404);
        $this->assertReviewerNesting($assignment, $form, $artifact);
    }

    private function fileResponse(ReviewFormArtifact $artifact, string $disposition): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($artifact->stored_file_path), 404);
        abort_unless((int) $disk->size($artifact->stored_file_path) === $artifact->file_size_bytes, 409);
        $storedHash = hash_file('sha256', $disk->path($artifact->stored_file_path));
        abort_unless(is_string($storedHash) && hash_equals($artifact->sha256, $storedHash), 409);

        return $disk->response(
            $artifact->stored_file_path,
            $artifact->original_file_name,
            [
                'Content-Type' => 'application/pdf',
                // Do not use CSP sandbox here: it prevents some browsers' native PDF
                // renderer from loading. Framing remains same-origin and the response is private.
                'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; base-uri 'none'",
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Referrer-Policy' => 'no-referrer',
                'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            ],
            $disposition,
        );
    }
}
