<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\CertificationState;
use App\Enums\ReviewCommentCategory;
use App\Enums\ReviewCommentScope;
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
use App\Models\ReviewSubmissionVersion;
use App\Services\Applications\ApplicationDocumentService;
use App\Services\Applications\ApplicationRevisionWorkflowService;
use App\Services\Certificates\ApplicantCertificateService;
use App\Services\Certificates\CertificationEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicantRevisionCertificateController extends Controller
{
    public function index(
        Request $request,
        CertificationEligibilityService $eligibility,
        ApplicationRevisionWorkflowService $revisionWorkflow,
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
        $selected->revisions()
            ->where('status', ApplicationRevisionStatus::PendingUploads->value)
            ->orderBy('revision_number')
            ->get()
            ->each(fn (ApplicationRevision $revision) => $revisionWorkflow
                ->ensureRevisionRequirements($request->user(), $selected, $revision));
        $selected->load([
            'decisionReleases' => fn ($releases) => $releases
                ->with([
                    'sourceReviewSubmission:id,decision_comment',
                    'releasedComments' => fn ($comments) => $comments
                        ->withTrashed()
                        ->with([
                            'assignment:id,review_cycle,assignment_sequence',
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
        $documentVersions = $selected->documents
            ->groupBy('document_requirement_id')
            ->map(fn ($versions) => $versions
                ->groupBy('document_version')
                ->map(fn ($physicalVersions) => $physicalVersions
                    ->sortByDesc(fn ($document): string => ($document->is_current ? '1' : '0').str_pad((string) $document->id, 12, '0', STR_PAD_LEFT))
                    ->first())
                ->sortByDesc('document_version')
                ->values());
        $documentLookup = $selected->documents->keyBy('id');
        $sourceVersionIds = $selected->decisionReleases
            ->flatMap(fn ($release) => collect($release->source_review_submission_version_ids ?: [
                $release->source_review_submission_version_id,
            ]))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $sourceVersions = ReviewSubmissionVersion::query()
            ->whereKey($sourceVersionIds)
            ->get()
            ->keyBy('id');
        $releasedFeedback = $selected->decisionReleases
            ->sortBy('review_cycle')
            ->values()
            ->flatMap(function ($release) use ($documentLookup, $sourceVersions) {
                $snapshot = collect($release->released_feedback_snapshot ?? []);
                if ($snapshot->isEmpty()) {
                    $snapshot = $release->releasedComments->map(fn ($comment): array => [
                        'id' => $comment->id,
                        'reviewer_assignment_id' => $comment->reviewer_assignment_id,
                        'application_document_id' => $comment->application_document_id,
                        'scope' => $comment->scope->value,
                        'category' => $comment->category->value,
                        'page_number' => $comment->page_number,
                        'body' => $comment->body,
                        'deleted_at' => $comment->deleted_at?->toIso8601String(),
                    ]);
                }
                $assignmentSequences = $snapshot
                    ->pluck('reviewer_assignment_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->mapWithKeys(fn (mixed $assignmentId, int $index): array => [
                        (int) $assignmentId => $index + 1,
                    ]);
                $releaseLabel = (int) $release->review_cycle === 0
                    ? 'Initial decision'
                    : 'Revision '.$release->review_cycle.' decision';
                $comments = $snapshot
                    ->filter(fn (mixed $comment): bool => is_array($comment)
                        && blank(data_get($comment, 'deleted_at'))
                        && filled(data_get($comment, 'body')))
                    ->map(function (array $comment) use ($release, $releaseLabel, $assignmentSequences, $documentLookup): object {
                        $documentId = filled(data_get($comment, 'application_document_id'))
                            ? (int) data_get($comment, 'application_document_id')
                            : null;
                        $document = $documentId ? $documentLookup->get($documentId) : null;
                        $assignmentId = filled(data_get($comment, 'reviewer_assignment_id'))
                            ? (int) data_get($comment, 'reviewer_assignment_id')
                            : null;
                        $reviewerSequence = max(1, (int) (data_get($comment, 'reviewer_sequence')
                            ?: $assignmentSequences->get($assignmentId, 1)));

                        return (object) [
                            'release_id' => (int) $release->id,
                            'review_cycle' => (int) $release->review_cycle,
                            'release_label' => $releaseLabel,
                            'reviewer_sequence' => $reviewerSequence,
                            'reviewer_label' => 'Reviewer '.$reviewerSequence,
                            'application_document_id' => $documentId,
                            'document' => $document,
                            'document_requirement_id' => $document?->document_requirement_id,
                            'document_version' => $document?->document_version,
                            'scope' => ReviewCommentScope::tryFrom((string) data_get($comment, 'scope')) ?? ReviewCommentScope::Overall,
                            'category' => ReviewCommentCategory::tryFrom((string) data_get($comment, 'category')) ?? ReviewCommentCategory::General,
                            'page_number' => filled(data_get($comment, 'page_number')) ? (int) data_get($comment, 'page_number') : null,
                            'body' => trim((string) data_get($comment, 'body')),
                            'released_at' => $release->released_at,
                            'is_decision_comment' => false,
                        ];
                    })
                    ->values();
                $versionIds = collect($release->source_review_submission_version_ids ?: [
                    $release->source_review_submission_version_id,
                ])->filter()->values();

                foreach ($versionIds as $index => $versionId) {
                    $decisionComment = trim((string) $sourceVersions->get((int) $versionId)?->decision_comment);
                    $reviewerSequence = $index + 1;
                    if ($decisionComment === '' || $comments->contains(
                        fn (object $comment): bool => $comment->reviewer_sequence === $reviewerSequence
                            && $comment->application_document_id === null
                            && mb_strtolower($comment->body) === mb_strtolower($decisionComment),
                    )) {
                        continue;
                    }
                    $comments->push((object) [
                        'release_id' => (int) $release->id,
                        'review_cycle' => (int) $release->review_cycle,
                        'release_label' => $releaseLabel,
                        'reviewer_sequence' => $reviewerSequence,
                        'reviewer_label' => 'Reviewer '.$reviewerSequence,
                        'application_document_id' => null,
                        'document' => null,
                        'document_requirement_id' => null,
                        'document_version' => null,
                        'scope' => ReviewCommentScope::Overall,
                        'category' => ReviewCommentCategory::General,
                        'page_number' => null,
                        'body' => $decisionComment,
                        'released_at' => $release->released_at,
                        'is_decision_comment' => true,
                    ]);
                }

                if ($versionIds->isEmpty()) {
                    $decisionComment = trim((string) $release->sourceReviewSubmission?->decision_comment);
                    if ($decisionComment !== '' && ! $comments->contains(
                        fn (object $comment): bool => $comment->application_document_id === null
                            && mb_strtolower($comment->body) === mb_strtolower($decisionComment),
                    )) {
                        $comments->push((object) [
                            'release_id' => (int) $release->id,
                            'review_cycle' => (int) $release->review_cycle,
                            'release_label' => $releaseLabel,
                            'reviewer_sequence' => 1,
                            'reviewer_label' => 'Reviewer 1',
                            'application_document_id' => null,
                            'document' => null,
                            'document_requirement_id' => null,
                            'document_version' => null,
                            'scope' => ReviewCommentScope::Overall,
                            'category' => ReviewCommentCategory::General,
                            'page_number' => null,
                            'body' => $decisionComment,
                            'released_at' => $release->released_at,
                            'is_decision_comment' => true,
                        ]);
                    }
                }

                return $comments;
            })
            ->values();
        $reviewerGroups = fn ($comments) => collect($comments)
            ->groupBy(fn (object $comment): string => $comment->release_id.'-'.$comment->reviewer_sequence)
            ->map(fn ($reviewerComments): array => [
                'label' => $reviewerComments->first()->reviewer_label,
                'release_label' => $reviewerComments->first()->release_label,
                'comments' => $reviewerComments->values(),
            ])
            ->values();
        $releasedReviewerGroups = $reviewerGroups($releasedFeedback);
        $requirementFeedbackGroups = $documentVersions
            ->map(function ($versions, $requirementId) use ($releasedFeedback, $reviewerGroups): array {
                $comments = $releasedFeedback->where('document_requirement_id', (int) $requirementId);

                return [
                    'key' => 'requirement-'.$requirementId,
                    'name' => $versions->first()?->requirement?->name ?? 'Supporting Document',
                    'versions' => $versions->values(),
                    'reviewer_groups' => collect(),
                    'reviewer_groups_by_version' => $versions->mapWithKeys(
                        fn ($document): array => [
                            $document->id => $reviewerGroups($comments->where('application_document_id', $document->id)),
                        ],
                    ),
                    'comment_count' => $comments->count(),
                ];
            })
            ->sortBy('name')
            ->values();
        $overallComments = $releasedFeedback->whereNull('application_document_id');
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
    ): RedirectResponse|JsonResponse {
        $certificates->submitSurvey($request->user(), $researchApplication, $request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Evaluation completed. Your released certificates are ready to claim.',
                'claim_url' => route('applicant.revision-certificates.certificate.claim', $researchApplication),
            ]);
        }

        return back()->with('status', 'Evaluation completed. Your released certificate is now ready to claim.');
    }

    public function claim(
        Request $request,
        ResearchApplication $researchApplication,
        ApplicantCertificateService $certificates,
    ): RedirectResponse|JsonResponse {
        $certificates->claim($request->user(), $researchApplication);

        if ($request->expectsJson()) {
            $researchApplication->load(['certificates.currentVersion']);

            return response()->json([
                'message' => 'Certificates claimed.',
                'certificates' => $researchApplication->certificates
                    ->filter(fn (Certificate $certificate): bool => $certificate->status === CertificateStatus::Claimed
                        && $certificate->claimed_by_user_id === $request->user()->id
                        && $certificate->currentVersion?->status === CertificateVersionStatus::Ready)
                    ->values()
                    ->map(fn (Certificate $certificate): array => [
                        'recipient_name' => $certificate->recipient_name ?: $researchApplication->applicant?->name,
                        'certificate_number' => $certificate->certificate_number,
                        'version' => $certificate->currentVersion->certificate_version,
                        'preview_url' => route('applicant.revision-certificates.certificate.preview', [$researchApplication, $certificate, $certificate->currentVersion]),
                        'download_url' => route('applicant.revision-certificates.certificate.download', [$researchApplication, $certificate, $certificate->currentVersion]),
                    ]),
                'download_all_url' => route('applicant.revision-certificates.certificates.download-all', $researchApplication),
            ]);
        }

        return back()->with('status', 'Certificate claimed. You may now view or download the current version.');
    }

    public function downloadAll(Request $request, ResearchApplication $researchApplication): Response
    {
        Gate::forUser($request->user())->authorize('viewRevisionCertification', $researchApplication);
        $researchApplication->load(['certificates.currentVersion']);
        $certificates = $researchApplication->certificates->filter(
            fn (Certificate $certificate): bool => $certificate->status === CertificateStatus::Claimed
                && $certificate->claimed_by_user_id === $request->user()->id
                && $certificate->claimed_certificate_version_id === $certificate->currentVersion?->id
                && $certificate->currentVersion?->status === CertificateVersionStatus::Ready
                && $certificate->currentVersion?->claimed_by_user_id === $request->user()->id,
        );
        abort_if($certificates->isEmpty(), 404);

        $disk = Storage::disk('local');
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        foreach ($certificates as $certificate) {
            $version = $certificate->currentVersion;
            abort_unless($disk->exists($version->stored_file_path), 404);
            $actualHash = hash_file('sha256', $disk->path($version->stored_file_path));
            abort_unless(is_string($actualHash) && hash_equals($version->sha256, $actualHash), 404);
            $pageCount = $pdf->setSourceFile($disk->path($version->stored_file_path));
            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }

        $bytes = $pdf->Output('S');
        abort_unless(is_string($bytes) && str_starts_with($bytes, '%PDF-'), 500);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$researchApplication->application_code.'-all-certificates.pdf"',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
        ]);
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
