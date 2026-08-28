<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\BulkReleaseType;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResLead\ReleaseApplicationDecisionRequest;
use App\Models\Certificate;
use App\Models\CertificateVersion;
use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationRevisionWorkflowService;
use App\Services\Applications\ReviewConsensusService;
use App\Services\Certificates\BulkReleaseService;
use App\Services\Certificates\CertificateReleaseService;
use App\Services\Certificates\CertificationEligibilityService;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResCertificationController extends Controller
{
    public function index(
        Request $request,
        CertificationEligibilityService $eligibility,
        BulkReleaseService $bulkReleases,
        ReviewConsensusService $consensus,
        AcademicTermResolver $terms,
    ): View {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'decision' => ['nullable', Rule::enum(ReviewDecision::class)],
            'claim' => ['nullable', Rule::in(['claimed', 'unclaimed', 'unavailable'])],
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')],
        ]);
        $queueStatuses = [
            ApplicationStatus::ReviewSubmittedPendingRelease,
            ApplicationStatus::Failed,
            ApplicationStatus::ResultReleasedAccepted,
            ApplicationStatus::ForCertificateRelease,
            ApplicationStatus::Exempted,
        ];
        $relevantApplications = ResearchApplication::query()
            ->where(function (Builder $query) use ($queueStatuses): void {
                $query->whereIn('application_status', ApplicationStatus::values($queueStatuses))
                    ->orWhere(function (Builder $incompleteRelease): void {
                        $incompleteRelease
                            ->where('application_status', ApplicationStatus::CertificateReleased->value)
                            ->whereHas('certificates')
                            ->whereHas('certificateRecipients', fn (Builder $recipients) => $recipients
                                ->whereDoesntHave('certificate', fn (Builder $certificates) => $this->releasedCertificate($certificates)));
                    });
            });
        $terms->applyFilters($relevantApplications, $filters);
        (clone $relevantApplications)
            ->where('application_status', ApplicationStatus::ReviewSubmittedPendingRelease->value)
            ->select('id')
            ->eachById(fn (ResearchApplication $application) => $consensus->evaluate($application), 100);
        $queueMetrics = [
            'pending_decision_release' => (clone $relevantApplications)
                ->where('application_status', ApplicationStatus::ReviewSubmittedPendingRelease->value)
                ->count(),
            'pending_certificate_release' => (clone $relevantApplications)
                ->whereIn('application_status', [
                    ApplicationStatus::ResultReleasedAccepted->value,
                    ApplicationStatus::ForCertificateRelease->value,
                    ApplicationStatus::Exempted->value,
                    ApplicationStatus::CertificateReleased->value,
                ])
                ->whereHas('certificateRecipients', fn (Builder $recipients) => $recipients
                    ->whereDoesntHave('certificate', fn (Builder $certificates) => $this->releasedCertificate($certificates)))
                ->count(),
            'final_revision_failed' => (clone $relevantApplications)
                ->where('application_status', ApplicationStatus::Failed->value)
                ->count(),
        ];
        $applications = (clone $relevantApplications)
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['q']);
                $query->where(fn (Builder $matching) => $matching
                    ->where('application_code', 'like', "%{$search}%")
                    ->orWhere('research_title', 'like', "%{$search}%"));
            })
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query
                ->where('application_status', $filters['status']))
            ->when(filled($filters['decision'] ?? null), function (Builder $query) use ($filters): void {
                $decision = (string) $filters['decision'];
                $query->where(fn (Builder $matching) => $matching
                    ->where('review_consensus_decision', $decision)
                    ->orWhereHas('decisionReleases', fn (Builder $releases) => $releases->where('decision', $decision)));
            })
            ->when(($filters['claim'] ?? null) === 'claimed', fn (Builder $query) => $query
                ->whereHas('certificateRecipients')
                ->whereDoesntHave('certificateRecipients', fn (Builder $recipients) => $recipients
                    ->whereDoesntHave('certificate', fn (Builder $certificates) => $this->claimedCertificate($certificates))))
            ->when(($filters['claim'] ?? null) === 'unclaimed', fn (Builder $query) => $query
                ->whereHas('certificateRecipients')
                ->whereDoesntHave('certificateRecipients', fn (Builder $recipients) => $recipients
                    ->whereDoesntHave('certificate', fn (Builder $certificates) => $this->releasedCertificate($certificates)))
                ->whereHas('certificates', fn (Builder $certificates) => $certificates
                    ->where('status', CertificateStatus::Released->value)))
            ->when(($filters['claim'] ?? null) === 'unavailable', fn (Builder $query) => $query
                ->whereHas('certificateRecipients', fn (Builder $recipients) => $recipients
                    ->whereDoesntHave('certificate', fn (Builder $certificates) => $this->releasedCertificate($certificates))))
            ->with([
                'certificateRecipients:id,research_application_id,sort_order',
                'certificates.recipient:id,recipient_name',
                'certificates.currentVersion:id,certificate_id,certificate_version,status,generated_at,issued_date,valid_until,certificate_background_id',
                'certificates.versions' => fn ($versions) => $versions
                    ->with('background:id,asset_version,source_kind')
                    ->orderByDesc('certificate_version'),
                'documents' => fn ($documents) => $documents
                    ->where('is_current', true)
                    ->with('requirement:id,name')
                    ->orderBy('document_requirement_id')
                    ->orderBy('id'),
                'decisionReleases' => fn ($releases) => $releases->latest('review_cycle'),
                'reviewerAssignments' => fn ($assignments) => $assignments
                    ->current()
                    ->with([
                        'reviewSubmission.currentVersion',
                    ])
                    ->orderBy('review_cycle')
                    ->orderBy('id'),
            ])
            ->orderByRaw('CASE WHEN application_status = ? THEN 0 ELSE 1 END', [ApplicationStatus::Failed->value])
            ->orderByRaw('CASE WHEN review_consensus_status = ? THEN 0 ELSE 1 END', [ReviewConsensusStatus::Conflicted->value])
            ->latest('status_updated_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $states = $applications->getCollection()->mapWithKeys(
            fn (ResearchApplication $application): array => [$application->id => $eligibility->state($application)],
        );

        return view('dashboard.certificates.res-index', [
            'pageTitle' => 'Decision & Certificates',
            'applications' => $applications,
            'queueMetrics' => $queueMetrics,
            'certificationStates' => $states,
            'filters' => $filters,
            'queueStatuses' => $queueStatuses,
            'termOptions' => $terms->filterOptions(),
            'bulkEligibleCounts' => $bulkReleases->eligibleCounts($request->user()),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Decision & Certificates'],
            ],
        ]);
    }

    public function releaseDecision(
        ReleaseApplicationDecisionRequest $request,
        ResearchApplication $researchApplication,
        ApplicationRevisionWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->releaseDecision(
            $request->user(),
            $researchApplication,
        );

        return back()->with('status', 'The application decision and its comments were released to the Applicant.');
    }

    public function workspace(
        Request $request,
        ResearchApplication $researchApplication,
        ReviewConsensusService $consensus,
    ): View {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $researchApplication = $consensus->evaluate($researchApplication);
        $cycle = max(0, ((int) $researchApplication->current_revision_cycle) - 1);
        $researchApplication->load([
            'documents' => fn ($documents) => $documents
                ->where('is_current', true)
                ->with('requirement:id,name')
                ->orderBy('document_requirement_id')
                ->orderBy('id'),
            'reviewerAssignments' => fn ($assignments) => $assignments
                ->current()
                ->where('review_cycle', $cycle)
                ->with([
                    'reviewer:id,name',
                    'reviewSubmission.currentVersion.artifacts.formSubmission',
                ])
                ->orderBy('assignment_sequence')
                ->orderBy('id'),
        ]);

        return view('dashboard.certificates.res-workspace', [
            'pageTitle' => 'Read-only Review Workspace',
            'application' => $researchApplication,
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Decision & Certificates', 'route' => 'res.certificates.index'],
                ['label' => $researchApplication->application_code],
            ],
        ]);
    }

    public function releaseCertificate(
        Request $request,
        ResearchApplication $researchApplication,
        CertificateReleaseService $certificates,
    ): RedirectResponse {
        $result = $certificates->release($request->user(), $researchApplication);

        return back()->with('status', $result['action'] === 'skipped'
            ? 'The current certificate was already released; no duplicate was created.'
            : 'Certificate generated from the official template and released securely.');
    }

    public function bulkRelease(Request $request, BulkReleaseService $bulkReleases): RedirectResponse
    {
        $validated = $request->validateWithBag('bulkRelease', [
            'release_type' => ['required', Rule::enum(BulkReleaseType::class)],
            'confirmation' => ['required', Rule::in(['release_all_eligible'])],
        ]);
        $summary = $bulkReleases->release(
            $request->user(),
            BulkReleaseType::from($validated['release_type']),
        );

        return back()->with('bulk_certificate_summary', $summary);
    }

    public function regenerate(
        Request $request,
        ResearchApplication $researchApplication,
        CertificateReleaseService $certificates,
    ): RedirectResponse {
        $request->validate(['confirmation' => ['required', Rule::in(['regenerate'])]]);
        $certificates->release($request->user(), $researchApplication, true);

        return back()->with('status', 'A new certificate version was generated. Prior issued versions remain unchanged.');
    }

    public function previewCertificate(
        Request $request,
        Certificate $certificate,
        CertificateVersion $certificateVersion,
    ): StreamedResponse {
        return $this->certificateResponse($request, $certificate, $certificateVersion, 'inline');
    }

    public function downloadCertificate(
        Request $request,
        Certificate $certificate,
        CertificateVersion $certificateVersion,
    ): StreamedResponse {
        return $this->certificateResponse($request, $certificate, $certificateVersion, 'attachment');
    }

    public function previewAllCertificates(Request $request, ResearchApplication $researchApplication): Response
    {
        return $this->allCertificatesResponse($request, $researchApplication, 'inline');
    }

    public function downloadAllCertificates(Request $request, ResearchApplication $researchApplication): Response
    {
        return $this->allCertificatesResponse($request, $researchApplication, 'attachment');
    }

    private function allCertificatesResponse(
        Request $request,
        ResearchApplication $researchApplication,
        string $disposition,
    ): Response {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $researchApplication->load(['certificates.currentVersion']);
        $certificates = $researchApplication->certificates->filter(
            fn (Certificate $certificate): bool => in_array($certificate->status, [CertificateStatus::PendingRelease, CertificateStatus::Released, CertificateStatus::Claimed], true)
                && $certificate->currentVersion?->status === CertificateVersionStatus::Ready,
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
            $pages = $pdf->setSourceFile($disk->path($version->stored_file_path));
            for ($page = 1; $page <= $pages; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }
        $bytes = $pdf->Output('S');
        abort_unless(is_string($bytes) && str_starts_with($bytes, '%PDF-'), 500);
        $fileName = $researchApplication->application_code.'-all-certificates.pdf';

        return response($bytes, 200, [
            ...$this->privateHeaders('application/pdf'),
            'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
        ]);
    }

    private function certificateResponse(
        Request $request,
        Certificate $certificate,
        CertificateVersion $version,
        string $disposition,
    ): StreamedResponse {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        abort_unless($version->certificate_id === $certificate->id, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($version->stored_file_path), 404);

        return $disk->response(
            $version->stored_file_path,
            $version->original_file_name,
            $this->privateHeaders('application/pdf'),
            $disposition,
        );
    }

    /** @return array<string, string> */
    private function privateHeaders(string $mimeType): array
    {
        return [
            'Content-Type' => $mimeType,
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
    }

    private function releasedCertificate(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [CertificateStatus::Released->value, CertificateStatus::Claimed->value])
            ->whereHas('currentVersion', fn (Builder $versions) => $versions
                ->where('status', CertificateVersionStatus::Ready->value));
    }

    private function claimedCertificate(Builder $query): Builder
    {
        return $query
            ->where('status', CertificateStatus::Claimed->value)
            ->whereHas('currentVersion', fn (Builder $versions) => $versions
                ->where('status', CertificateVersionStatus::Ready->value));
    }
}
