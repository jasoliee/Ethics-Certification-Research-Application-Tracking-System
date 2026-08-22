<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\CertificationState;
use App\Enums\ReviewFormType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\StoreApplicantSurveyRequest;
use App\Http\Requests\Applicant\SubmitApplicantRevisionRequest;
use App\Http\Requests\Applicant\UploadRevisionDocumentRequest;
use App\Models\ApplicationRevision;
use App\Models\ApplicationRevisionRequirement;
use App\Models\Certificate;
use App\Models\CertificateVersion;
use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationDocumentService;
use App\Services\Applications\ApplicationRevisionWorkflowService;
use App\Services\Certificates\ApplicantCertificateService;
use App\Services\Certificates\CertificationEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicantRevisionCertificateController extends Controller
{
    public function index(
        Request $request,
        CertificationEligibilityService $eligibility,
    ): View {
        $filters = $request->validate([
            'application' => ['nullable', 'integer'],
        ]);
        $query = ResearchApplication::query()
            ->where('applicant_user_id', $request->user()->id)
            ->where(function (Builder $query): void {
                $query->whereIn('application_status', [
                    ApplicationStatus::ReviewSubmittedPendingRelease->value,
                    ApplicationStatus::ResultReleasedAccepted->value,
                    ApplicationStatus::ForCertificateRelease->value,
                    ApplicationStatus::ResultReleasedMinorRevision->value,
                    ApplicationStatus::ResultReleasedMajorRevision->value,
                    ApplicationStatus::ResultReleasedDisapproved->value,
                    ApplicationStatus::RevisionWindowOpen->value,
                    ApplicationStatus::RevisionSubmitted->value,
                    ApplicationStatus::UnderReReview->value,
                    ApplicationStatus::Exempted->value,
                    ApplicationStatus::CertificateReleased->value,
                ])->orWhereHas('revisions')->orWhereHas('certificates');
            });
        $applications = (clone $query)
            ->select(['id', 'academic_term_id', 'application_code', 'research_title', 'application_status', 'current_stage', 'status_updated_at'])
            ->latest('status_updated_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $selectedId = isset($filters['application']) ? (int) $filters['application'] : $applications->first()?->id;
        $selected = $selectedId
            ? (clone $query)->whereKey($selectedId)->firstOrFail()
            : null;

        $viewData = [
            'pageTitle' => 'Revision and Certificates',
            'applications' => $applications,
            'selectedApplication' => $selected,
            'filters' => $filters,
            'latestRelease' => null,
            'activeRevision' => null,
            'releasedReviewerGroups' => collect(),
            'documentVersions' => collect(),
            'requirementFeedbackGroups' => collect(),
            'worksheetGroups' => collect(),
            'certificationState' => null,
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Revision and Certificates'],
            ],
        ];

        if (! $selected) {
            return view('dashboard.applications.revision-certificates', $viewData);
        }

        Gate::authorize('viewRevisionCertification', $selected);
        $selected->load([
            'decisionReleases' => fn ($releases) => $releases
                ->with([
                    'releasedComments' => fn ($comments) => $comments
                        ->withTrashed()
                        ->with([
                            'assignment:id,review_cycle',
                            'document:id,document_requirement_id,document_version,uploaded_at',
                            'document.requirement:id,name',
                        ])
                        ->orderBy('created_at')
                        ->orderBy('id'),
                ])
                ->orderByDesc('review_cycle'),
            'revisions' => fn ($revisions) => $revisions
                ->with([
                    'requirements' => fn ($requirements) => $requirements
                        ->with(['requirement:id,name', 'sourceDocument', 'replacementDocument'])
                        ->orderBy('id'),
                ])
                ->orderByDesc('revision_number'),
            'documents' => fn ($documents) => $documents
                ->with('requirement:id,name')
                ->orderBy('document_requirement_id')
                ->orderByDesc('document_version')
                ->orderByDesc('id'),
            'certificates' => fn ($certificates) => $certificates
                ->with('currentVersion.background:id,asset_version,source_kind')
                ->orderBy('id'),
            'surveyResponse',
        ]);

        $latestRelease = $selected->decisionReleases->first();
        $releasedComments = $latestRelease?->releasedComments ?? collect();
        $reviewerLabels = $releasedComments
            ->pluck('reviewer_assignment_id')
            ->filter()
            ->unique()
            ->values()
            ->mapWithKeys(fn (int $assignmentId, int $index): array => [
                $assignmentId => 'Reviewer '.($index + 1),
            ]);
        $reviewerGroups = fn ($comments) => $comments
            ->groupBy('reviewer_assignment_id')
            ->map(fn ($reviewerComments, $assignmentId): array => [
                'label' => $reviewerLabels->get($assignmentId, 'Reviewer'),
                'comments' => $reviewerComments,
            ])
            ->values();
        $releasedReviewerGroups = $reviewerGroups($releasedComments);
        $documentVersions = $selected->documents
            ->groupBy('document_requirement_id')
            ->map(fn ($versions) => $versions
                ->groupBy('document_version')
                ->map(fn ($physicalVersions) => $physicalVersions
                    ->sortByDesc(fn ($document): string => ($document->is_current ? '1' : '0').str_pad((string) $document->id, 12, '0', STR_PAD_LEFT))
                    ->first())
                ->sortByDesc('document_version')
                ->values());
        $requirementFeedbackGroups = $documentVersions
            ->map(function ($versions, $requirementId) use ($releasedComments, $reviewerGroups): array {
                $comments = $releasedComments->filter(
                    fn ($comment): bool => (int) $comment->document?->document_requirement_id === (int) $requirementId,
                );

                return [
                    'key' => 'requirement-'.$requirementId,
                    'name' => $versions->first()?->requirement?->name ?? 'Supporting Document',
                    'versions' => $versions->values(),
                    'reviewer_groups' => $reviewerGroups($comments),
                    'comment_count' => $comments->count(),
                ];
            })
            ->sortBy('name')
            ->values();
        $overallComments = $releasedComments->filter(fn ($comment): bool => $comment->document === null);
        if ($overallComments->isNotEmpty()) {
            $requirementFeedbackGroups->prepend([
                'key' => 'overall-application',
                'name' => 'Overall Application',
                'versions' => collect(),
                'reviewer_groups' => $reviewerGroups($overallComments),
                'comment_count' => $overallComments->count(),
            ]);
        }
        $worksheetAssignments = $latestRelease
            ? $selected->reviewerAssignments()
                ->where('review_cycle', $latestRelease->review_cycle)
                ->with([
                    'reviewSubmissionVersions' => fn ($versions) => $versions
                        ->with(['artifacts.formSubmission'])
                        ->orderBy('version_number'),
                ])
                ->orderBy('assignment_sequence')
                ->orderBy('id')
                ->get()
            : collect();
        $worksheetGroups = collect(ReviewFormType::cases())->map(function (ReviewFormType $type) use ($worksheetAssignments): array {
            $artifacts = $worksheetAssignments->values()->flatMap(
                fn ($assignment, int $reviewerIndex) => $assignment->reviewSubmissionVersions->flatMap(
                    fn ($version) => $version->artifacts
                        ->filter(fn ($artifact): bool => $artifact->formSubmission?->form_type === $type)
                        ->map(fn ($artifact): array => [
                            'artifact' => $artifact,
                            'assignment' => $assignment,
                            'reviewer_label' => 'Reviewer '.($reviewerIndex + 1),
                            'version_number' => $artifact->business_version ?? (((int) $assignment->review_cycle) + 1),
                            'internal_version_number' => $version->version_number,
                        ]),
                ),
            )
                ->groupBy(fn (array $entry): string => $entry['assignment']->id.'-'.$entry['version_number'])
                ->map(fn ($duplicates) => $duplicates->sortByDesc(fn (array $entry): int => $entry['artifact']->id)->first())
                ->values();

            return ['type' => $type, 'artifacts' => $artifacts];
        })->filter(fn (array $group): bool => $group['artifacts']->isNotEmpty())->values();
        $activeRevision = $selected->revisions->first(
            fn (ApplicationRevision $revision): bool => in_array($revision->status, [
                ApplicationRevisionStatus::PendingUploads,
                ApplicationRevisionStatus::UnderReview,
            ], true),
        );

        return view('dashboard.applications.revision-certificates', [
            ...$viewData,
            'selectedApplication' => $selected,
            'latestRelease' => $latestRelease,
            'activeRevision' => $activeRevision,
            'releasedReviewerGroups' => $releasedReviewerGroups,
            'documentVersions' => $documentVersions,
            'requirementFeedbackGroups' => $requirementFeedbackGroups,
            'worksheetGroups' => $worksheetGroups,
            'certificationState' => $selected->application_status === ApplicationStatus::ForCertificateRelease
                ? CertificationState::PendingResRelease
                : $eligibility->state($selected),
        ]);
    }

    public function uploadRevision(
        UploadRevisionDocumentRequest $request,
        ResearchApplication $researchApplication,
        ApplicationRevision $applicationRevision,
        ApplicationRevisionRequirement $applicationRevisionRequirement,
        ApplicationDocumentService $documents,
    ): RedirectResponse {
        $documents->uploadRevision(
            $request->user(),
            $researchApplication,
            $applicationRevision,
            $applicationRevisionRequirement,
            $request->file('document'),
        );

        return back()->with('status', 'Revised document uploaded as a new protected version.');
    }

    public function submitRevision(
        SubmitApplicantRevisionRequest $request,
        ResearchApplication $researchApplication,
        ApplicationRevision $applicationRevision,
        ApplicationRevisionWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->submitRevision($request->user(), $researchApplication, $applicationRevision);

        return back()->with('status', 'Revision submitted directly to the authorized Reviewer set.');
    }

    public function submitSurvey(
        StoreApplicantSurveyRequest $request,
        ResearchApplication $researchApplication,
        ApplicantCertificateService $certificates,
    ): RedirectResponse {
        $certificates->submitSurvey($request->user(), $researchApplication, $request->validated());

        return back()->with('status', 'Evaluation completed. Your released certificate is now ready to claim.');
    }

    public function claim(
        Request $request,
        ResearchApplication $researchApplication,
        ApplicantCertificateService $certificates,
    ): RedirectResponse {
        $certificates->claim($request->user(), $researchApplication);

        return back()->with('status', 'Certificate claimed. You may now view or download the current version.');
    }

    public function preview(
        Request $request,
        ResearchApplication $researchApplication,
        Certificate $certificate,
        CertificateVersion $certificateVersion,
    ): StreamedResponse {
        $this->authorizeClaimedVersion($request, $researchApplication, $certificate, $certificateVersion);

        return $this->fileResponse($certificateVersion, 'inline');
    }

    public function download(
        Request $request,
        ResearchApplication $researchApplication,
        Certificate $certificate,
        CertificateVersion $certificateVersion,
    ): StreamedResponse {
        $this->authorizeClaimedVersion($request, $researchApplication, $certificate, $certificateVersion);

        return $this->fileResponse($certificateVersion, 'attachment');
    }

    private function authorizeClaimedVersion(
        Request $request,
        ResearchApplication $application,
        Certificate $certificate,
        CertificateVersion $version,
    ): void {
        Gate::forUser($request->user())->authorize('viewRevisionCertification', $application);
        abort_unless($certificate->research_application_id === $application->id, 404);
        abort_unless($version->certificate_id === $certificate->id, 404);
        abort_unless(
            $certificate->status === CertificateStatus::Claimed
            && $certificate->claimed_by_user_id === $request->user()->id
            && $certificate->claimed_certificate_version_id === $version->id
            && $version->status === CertificateVersionStatus::Ready
            && $version->claimed_by_user_id === $request->user()->id,
            403,
        );
    }

    private function fileResponse(CertificateVersion $version, string $disposition): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($version->stored_file_path), 404);

        return $disk->response($version->stored_file_path, $version->original_file_name, [
            'Content-Type' => 'application/pdf',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ], $disposition);
    }
}
