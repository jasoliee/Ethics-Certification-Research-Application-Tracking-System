<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ApplicantType;
use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\ResearchType;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\Privacy\ApplicationIdentityVisibilityService;
use App\Services\Reports\ApplicantSurveyReportService;
use App\Services\Reports\OperationalReportExportService;
use App\Services\Reports\OperationalReportService;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResReportController extends Controller
{
    public function index(
        Request $request,
        ApplicantSurveyReportService $surveyReports,
        OperationalReportService $operationalReports,
        AcademicTermResolver $terms,
    ): View {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);

        return view('dashboard.reports.res-index', [
            'pageTitle' => 'Reports',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Reports'],
            ],
            'surveySummary' => $surveyReports->summary($filters),
            'report' => $operationalReports->report($filters, 10),
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'researchTypes' => ResearchType::cases(),
            'applicantTypes' => ApplicantType::cases(),
            'reviewTypes' => ReviewType::cases(),
            'applicationStatuses' => ApplicationStatus::cases(),
            'certificateStatuses' => $this->certificateStatusOptions(),
            'institutes' => $this->instituteOptions(),
        ]);
    }

    public function applications(Request $request, OperationalReportService $operationalReports): View
    {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);

        return view('dashboard.reports.applications', [
            'pageTitle' => 'Filtered Applications',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Reports', 'route' => 'res.reports.index', 'parameters' => $filters],
                ['label' => 'Filtered Applications'],
            ],
            'applications' => $operationalReports->allApplicationRows($filters),
            'filters' => $filters,
            'filterSummary' => $this->filterSummary($filters),
        ]);
    }

    public function downloadReport(
        Request $request,
        ApplicantSurveyReportService $surveyReports,
        OperationalReportExportService $exports,
        OperationalReportService $operationalReports,
    ): Response {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);
        $format = validator($request->query(), ['format' => ['required', Rule::in(['xlsx', 'pdf'])]])->validate()['format'];
        $report = $operationalReports->report($filters);
        $survey = $surveyReports->summary($filters);
        $bytes = $format === 'xlsx'
            ? $exports->reportExcel($report, $survey, $this->filterSummary($filters))
            : $exports->reportPdf($report, $this->filterSummary($filters));

        return response($bytes, 200, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ecrats-reu-report-'.now()->format('Ymd-His').'.'.$format.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadSurvey(
        Request $request,
        ApplicantSurveyReportService $surveyReports,
        OperationalReportExportService $exports,
    ): Response {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);
        $format = validator($request->query(), ['format' => ['required', Rule::in(['xlsx', 'pdf'])]])->validate()['format'];
        $survey = $surveyReports->summary($filters);
        $bytes = $format === 'xlsx'
            ? $exports->surveyExcel($survey, $this->filterSummary($filters))
            : $exports->surveyPdf($survey, $this->filterSummary($filters));

        return response($bytes, 200, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ecrats-applicant-feedback-'.now()->format('Ymd-His').'.'.$format.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function printReport(
        Request $request,
        OperationalReportExportService $exports,
        OperationalReportService $operationalReports,
    ): Response {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);
        $bytes = $exports->reportPdf(
            $operationalReports->report($filters),
            $this->filterSummary($filters),
        );

        return $this->inlinePdfResponse(
            $bytes,
            'ecrats-reu-report-'.now()->format('Ymd-His').'.pdf',
        );
    }

    public function printSurvey(
        Request $request,
        ApplicantSurveyReportService $surveyReports,
        OperationalReportExportService $exports,
    ): Response {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);
        $bytes = $exports->surveyPdf(
            $surveyReports->summary($filters),
            $this->filterSummary($filters),
        );

        return $this->inlinePdfResponse(
            $bytes,
            'ecrats-applicant-feedback-'.now()->format('Ymd-His').'.pdf',
        );
    }

    public function applicant(
        Request $request,
        User $applicant,
        ApplicationIdentityVisibilityService $identityVisibility,
    ): View {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        abort_unless($applicant->role === UserRole::Applicant, 404);
        abort_unless($identityVisibility->applicantIsVisible($applicant), 404);
        $filters = $this->validatedFilters($request);

        $applications = $identityVisibility->forApplicant($applicant)
            ->when($request->integer('application') > 0, fn ($applications) => $applications
                ->whereKey($request->integer('application')))
            ->with([
                'certificates:id,research_application_id,recipient_name,certificate_number,status,current_certificate_version_id,released_at,claimed_at',
                'certificates.currentVersion:id,certificate_id,status,stored_file_path,original_file_name',
            ])
            ->latest('submitted_at')
            ->get([
                'id',
                'application_code',
                'research_title',
                'institution',
                'application_status',
                'review_type',
                'submitted_at',
            ]);
        abort_if($applications->isEmpty(), 404);

        return view('dashboard.reports.applicant', [
            'pageTitle' => 'Released Applicant Record',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Reports', 'route' => 'res.reports.index'],
                ['label' => 'Released Applicant Record'],
            ],
            'applicant' => $applicant,
            'applications' => $applications,
            'backToReportsUrl' => route('res.reports.index', $filters),
        ]);
    }

    public function certifications(Request $request, OperationalReportService $operationalReports): View
    {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);

        return view('dashboard.reports.certifications', [
            'pageTitle' => 'Applicant Certification',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Reports', 'route' => 'res.reports.index', 'parameters' => $filters],
                ['label' => 'Applicant Certification'],
            ],
            'rows' => $operationalReports->allApplicantCertificationRows($filters),
            'filters' => $filters,
        ]);
    }

    public function instituteApplicants(Request $request, OperationalReportService $operationalReports): View
    {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);
        $institute = validator($request->query(), ['institute' => ['required', 'string', 'max:150']])->validate()['institute'];

        return view('dashboard.reports.institute-applicants', [
            'pageTitle' => 'Institute Applicant List',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Reports', 'route' => 'res.reports.index', 'parameters' => $filters],
                ['label' => 'Institute Applicant List'],
            ],
            'institute' => $institute,
            'rows' => $operationalReports->instituteApplicantRows($institute, $filters),
            'filters' => $filters,
        ]);
    }

    public function auditIndex(Request $request, AcademicTermResolver $terms): View
    {
        Gate::authorize('viewAuditLog', User::class);
        $filters = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'result' => ['nullable', 'string', 'max:100'],
            'target_type' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'semester' => ['nullable', 'string', 'max:50'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ])->validate();
        $hiddenActions = ['user.onboarding_completed', 'user.password_setup_completed'];
        $search = trim((string) ($filters['search'] ?? ''));
        $baseQuery = AuditLog::query()->whereNotIn('action', $hiddenActions);
        $actions = (clone $baseQuery)->distinct()->orderBy('action')->pluck('action');
        $targetTypes = (clone $baseQuery)->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type');
        $logsQuery = AuditLog::query()
            ->select(['id', 'actor_user_id', 'action', 'subject_type', 'subject_id', 'metadata', 'created_at'])
            ->with('actor:id,name,username,role')
            ->whereNotIn('action', $hiddenActions);
        $terms->applyFilters($logsQuery, $filters);
        $logs = $logsQuery
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($matches) use ($search): void {
                    $matches->whereLike('action', '%'.$search.'%')
                        ->orWhereHas('actor', fn ($actors) => $actors
                            ->whereLike('name', '%'.$search.'%')
                            ->orWhereLike('username', '%'.$search.'%'));
                });
            })
            ->when(filled($filters['action'] ?? null), fn ($query) => $query->where('action', $filters['action']))
            ->when(filled($filters['role'] ?? null), fn ($query) => $query->whereHas('actor', fn ($actors) => $actors->where('role', $filters['role'])))
            ->when(filled($filters['result'] ?? null), fn ($query) => $query->where('metadata->result', $filters['result']))
            ->when(filled($filters['target_type'] ?? null), fn ($query) => $query->where('subject_type', $filters['target_type']))
            ->when(filled($filters['date_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('identity.users.audit', [
            'pageTitle' => 'Audit Log',
            'logs' => $logs,
            'filters' => $filters,
            'actions' => $actions,
            'targetTypes' => $targetTypes,
            'termOptions' => $terms->filterOptions(),
            'routeBase' => 'res.reports',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Reports', 'route' => 'res.reports.index'],
                ['label' => 'Audit Log'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request): array
    {
        return validator($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'research_type' => ['nullable', Rule::enum(ResearchType::class)],
            'applicant_type' => ['nullable', Rule::enum(ApplicantType::class)],
            'review_type' => ['nullable', Rule::enum(ReviewType::class)],
            'institute' => ['nullable', 'string', 'max:150'],
            'application_status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'certificate_status' => ['nullable', Rule::in([
                CertificateStatus::PendingRelease->value,
                CertificateStatus::GenerationFailed->value,
                CertificateStatus::Released->value,
                CertificateStatus::Claimed->value,
                'issued',
                'unclaimed',
            ])],
            'summary_filter' => ['nullable', Rule::in([
                'unique_applicants',
                'submitted',
                'not_submitted',
                'failed',
                'certificates_claimed',
                'certificates_unclaimed',
            ])],
        ])->validate();
    }

    private function instituteOptions()
    {
        return ResearchApplication::query()
            ->whereNotNull('institution')
            ->where('institution', '<>', '')
            ->pluck('institution')
            ->merge(User::query()->whereNotNull('institution')->where('institution', '<>', '')->pluck('institution'))
            ->unique()
            ->sort()
            ->values();
    }

    /** @return array<string, string> */
    private function certificateStatusOptions(): array
    {
        return [
            'issued' => 'Issued (released or claimed)',
            'unclaimed' => 'Unclaimed',
            CertificateStatus::Claimed->value => 'Claimed',
            CertificateStatus::Released->value => 'Released',
            CertificateStatus::PendingRelease->value => 'Pending release',
            CertificateStatus::GenerationFailed->value => 'Generation failed',
        ];
    }

    /** @param array<string, mixed> $filters */
    private function filterSummary(array $filters): string
    {
        $active = collect($filters)->filter(fn (mixed $value): bool => filled($value));
        if ($active->isEmpty()) {
            return 'All Records';
        }

        return $active->map(function (mixed $value, string $key): string {
            $display = match ($key) {
                'academic_term_id' => AcademicTerm::query()->find((int) $value)?->filterLabel() ?? (string) $value,
                'research_type' => ResearchType::tryFrom((string) $value)?->label() ?? (string) $value,
                'applicant_type' => ApplicantType::tryFrom((string) $value)?->label() ?? (string) $value,
                'review_type' => ReviewType::tryFrom((string) $value)?->label() ?? (string) $value,
                'application_status' => ApplicationStatus::tryFrom((string) $value)?->label() ?? (string) $value,
                'certificate_status' => $this->certificateStatusOptions()[(string) $value] ?? Str::headline((string) $value),
                default => (string) $value,
            };

            return Str::headline($key).': '.$display;
        })->implode(' | ');
    }

    private function inlinePdfResponse(string $bytes, string $filename): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
