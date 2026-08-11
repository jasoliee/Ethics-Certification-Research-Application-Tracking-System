<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResLead\ReleaseApplicationDecisionRequest;
use App\Http\Requests\ResLead\UploadCertificateBackgroundRequest;
use App\Models\Certificate;
use App\Models\CertificateBackground;
use App\Models\CertificateVersion;
use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationRevisionWorkflowService;
use App\Services\Certificates\CertificateBackgroundService;
use App\Services\Certificates\CertificateReleaseService;
use App\Services\Certificates\CertificationEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResCertificationController extends Controller
{
    public function index(
        Request $request,
        CertificationEligibilityService $eligibility,
        CertificateBackgroundService $backgroundService,
    ): View {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'state' => ['nullable', Rule::in(['decision', 'eligible', 'released', 'failed', 'claimed'])],
        ]);
        $applications = ResearchApplication::query()
            ->where(function (Builder $query): void {
                $query->whereIn('application_status', [
                    ApplicationStatus::ReviewSubmittedPendingRelease->value,
                    ApplicationStatus::ResultReleasedAccepted->value,
                    ApplicationStatus::Exempted->value,
                    ApplicationStatus::CertificateReleased->value,
                ])->orWhereHas('certificate');
            })
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['q']);
                $query->where(fn (Builder $matching) => $matching
                    ->where('application_code', 'like', "%{$search}%")
                    ->orWhere('research_title', 'like', "%{$search}%")
                    ->orWhereHas('applicant', fn (Builder $applicants) => $applicants->where('name', 'like', "%{$search}%")));
            })
            ->when(($filters['state'] ?? null) === 'decision', fn (Builder $query) => $query
                ->where('application_status', ApplicationStatus::ReviewSubmittedPendingRelease->value))
            ->when(($filters['state'] ?? null) === 'eligible', fn (Builder $query) => $query
                ->whereIn('application_status', [
                    ApplicationStatus::ResultReleasedAccepted->value,
                    ApplicationStatus::Exempted->value,
                ]))
            ->when(($filters['state'] ?? null) === 'released', fn (Builder $query) => $query
                ->whereHas('certificate', fn (Builder $certificates) => $certificates->where('status', 'released')))
            ->when(($filters['state'] ?? null) === 'failed', fn (Builder $query) => $query
                ->whereHas('certificate', fn (Builder $certificates) => $certificates->where('status', 'generation_failed')))
            ->when(($filters['state'] ?? null) === 'claimed', fn (Builder $query) => $query
                ->whereHas('certificate', fn (Builder $certificates) => $certificates->where('status', 'claimed')))
            ->with([
                'applicant:id,name',
                'surveyResponse:id,research_application_id,completed_at',
                'certificate.currentVersion:id,certificate_id,certificate_version,status,generated_at,certificate_background_id',
                'certificate.versions' => fn ($versions) => $versions
                    ->with('background:id,asset_version,source_kind')
                    ->orderByDesc('certificate_version'),
                'decisionReleases' => fn ($releases) => $releases->latest('review_cycle'),
                'reviewerAssignments' => fn ($assignments) => $assignments
                    ->current()
                    ->with([
                        'reviewSubmission:id,reviewer_assignment_id,status,decision,submitted_at',
                        'comments' => fn ($comments) => $comments
                            ->with('document.requirement:id,name')
                            ->orderBy('created_at')
                            ->orderBy('id'),
                    ])
                    ->orderBy('review_cycle')
                    ->orderBy('id'),
            ])
            ->latest('status_updated_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $states = $applications->getCollection()->mapWithKeys(
            fn (ResearchApplication $application): array => [$application->id => $eligibility->state($application)],
        );
        $activeBackground = $backgroundService->active();
        $backgrounds = CertificateBackground::query()
            ->latest('asset_version')
            ->paginate(10, ['*'], 'background_page')
            ->withQueryString();

        return view('dashboard.certificates.res-index', [
            'pageTitle' => 'Certificate Processing',
            'applications' => $applications,
            'certificationStates' => $states,
            'backgrounds' => $backgrounds,
            'activeBackground' => $activeBackground,
            'filters' => $filters,
            'decisions' => ReviewDecision::cases(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Certificate Processing'],
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
            ReviewDecision::from($request->validated('decision')),
            $request->validated('comment_ids', []),
        );

        return back()->with('status', 'Decision and selected comments released to the Applicant.');
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

    public function bulkRelease(Request $request, CertificateReleaseService $certificates): RedirectResponse
    {
        $validated = $request->validate(['confirmation' => ['required', Rule::in(['release_all_eligible'])]]);
        unset($validated);
        $summary = $certificates->releaseAllEligible($request->user());

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

    public function uploadBackground(
        UploadCertificateBackgroundRequest $request,
        CertificateBackgroundService $backgrounds,
    ): RedirectResponse {
        $backgrounds->uploadAndActivate($request->user(), $request->file('background'));

        return back()->with('status', 'Background validated and activated for future certificate generations.');
    }

    public function activateBackground(
        Request $request,
        CertificateBackground $certificateBackground,
        CertificateBackgroundService $backgrounds,
    ): RedirectResponse {
        $backgrounds->activate($request->user(), $certificateBackground);

        return back()->with('status', 'Selected background activated for future certificate generations.');
    }

    public function resetBackground(Request $request, CertificateBackgroundService $backgrounds): RedirectResponse
    {
        $backgrounds->resetToOfficial($request->user());

        return back()->with('status', 'Official default background restored for future certificate generations.');
    }

    public function previewBackground(Request $request, CertificateBackground $certificateBackground): StreamedResponse
    {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($certificateBackground->stored_file_path), 404);

        return $disk->response(
            $certificateBackground->stored_file_path,
            $certificateBackground->original_file_name,
            $this->privateHeaders($certificateBackground->mime_type),
            'inline',
        );
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
}
