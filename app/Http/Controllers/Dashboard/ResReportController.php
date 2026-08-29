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
use App\Models\CertificateBackground;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\Certificates\CertificateBackgroundService;
use App\Services\Privacy\ApplicationIdentityVisibilityService;
use App\Services\Reports\ApplicantSurveyReportService;
use App\Services\Reports\OperationalReportService;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(
        Request $request,
        ApplicantSurveyReportService $surveyReports,
        OperationalReportService $operationalReports,
    ): StreamedResponse {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);
        $report = $operationalReports->report($filters);
        $survey = $surveyReports->summary($filters);

        return response()->streamDownload(function () use ($report, $survey, $filters): void {
            $output = fopen('php://output', 'wb');
            abort_unless(is_resource($output), 500);
            $write = function (array $row) use ($output): void {
                fputcsv($output, array_map(fn (mixed $value): string => $this->csvCell($value), $row), ',', '"', '', "\r\n");
            };

            $write(['ECRATS REU Operational Report']);
            $write(['Generated', now()->format('M j, Y g:i A')]);
            $write(['Reporting Scope', $this->filterSummary($filters)]);
            $write([]);
            $write(['Overall Summary']);
            foreach ($report['summary'] as $key => $value) {
                $write([Str::headline($key), $value]);
            }

            $write([]);
            $write(['Applicant and Application Summary by Institute']);
            $write(['Institute', 'Unique Applicants', 'Applications Submitted', 'Applicants Not Yet Submitted', 'Failed Applications', 'Certificates Claimed', 'Certificates Unclaimed']);
            foreach ($report['institute_summary'] as $row) {
                $write([$row['institute'], $row['unique_applicants'], $row['submitted'], $row['not_submitted'], $row['failed'], $row['claimed'], $row['unclaimed']]);
            }

            $write([]);
            $write(['Adviser and Reviewer Summary']);
            $write(['Institute', 'Research Advisers', 'Reviewer-enabled Advisers']);
            foreach ($report['adviser_reviewer_summary'] as $row) {
                $write([$row['institute'], $row['advisers'], $row['reviewers']]);
            }

            $write([]);
            $write(['Reviewer Review Workload']);
            $write(['Reviewer', 'Institute', 'Expedited', 'Full Board', 'Total Assigned', 'Completed', 'Pending', 'Overdue', 'Remaining Capacity']);
            foreach ($report['reviewer_workload'] as $row) {
                $write([$row['reviewer']->name, $row['institute'], $row['expedited'], $row['full_board'], $row['total'], $row['completed'], $row['pending'], $row['overdue'], $row['remaining']]);
            }

            $write([]);
            $write(['Applications (double-blind)']);
            $write(['Application Code', 'Research Title', 'Institute', 'Review Type', 'Workflow Status', 'Certificate Status', 'Submitted']);
            foreach ($report['applications'] as $row) {
                $application = $row['application'];
                $write([
                    $application->application_code,
                    $application->research_title,
                    $application->institution,
                    $application->review_type ? ReviewType::tryFrom((string) $application->review_type)?->label() : 'Not classified',
                    $application->statusLabel(),
                    $row['certificate_status'],
                    $application->submitted_at?->format('M j, Y g:i A'),
                ]);
            }

            $write([]);
            $write(['Applicant Certification']);
            $write(['Applicant', 'Institutional ID', 'Institute', 'Application Code', 'Recipient', 'Certificate Status', 'Released Date', 'Ageing']);
            foreach ($report['applicant_certification'] as $row) {
                $write([
                    $row['applicant']?->name,
                    $row['applicant']?->institutional_identifier,
                    $row['application']->institution,
                    $row['application']->application_code,
                    $row['certificate']->recipient_name,
                    $row['certificate']->status->label(),
                    $row['released_at']?->format('M j, Y g:i A'),
                    $row['ageing_days'] === null ? null : $row['ageing_days'].' days',
                ]);
            }

            $write([]);
            $write(['Applicant Feedback (anonymous aggregate only)']);
            $write(['Responses', $survey['response_count']]);
            $write(['Overall Average', $survey['overall_average'] === null ? 'No data' : $survey['overall_average'].' / 5']);
            foreach ($survey['sections'] as $section) {
                $write([$section['title'], $section['average'] === null ? 'No data' : $section['average'].' / 5']);
            }

            fclose($output);
        }, 'ecrats-reu-report-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function printReport(
        Request $request,
        ApplicantSurveyReportService $surveyReports,
        OperationalReportService $operationalReports,
        CertificateBackgroundService $backgrounds,
    ): View {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);

        return view('dashboard.reports.res-print', [
            'report' => $operationalReports->report($filters),
            'surveySummary' => $surveyReports->summary($filters),
            'filters' => $filters,
            'filterSummary' => $this->filterSummary($filters),
            'worksheetBackground' => $this->worksheetBackgroundDataUri($backgrounds),
            'generatedAt' => now(),
        ]);
    }

    public function printSurvey(
        Request $request,
        ApplicantSurveyReportService $surveyReports,
        CertificateBackgroundService $backgrounds,
    ): View {
        abort_unless($request->user()->role === UserRole::ResLead, 403);
        $filters = $this->validatedFilters($request);

        return view('dashboard.reports.survey-print', [
            'surveySummary' => $surveyReports->summary($filters),
            'filters' => $filters,
            'filterSummary' => $this->filterSummary($filters),
            'worksheetBackground' => $this->worksheetBackgroundDataUri($backgrounds),
            'generatedAt' => now(),
        ]);
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

    private function worksheetBackgroundDataUri(CertificateBackgroundService $backgrounds): ?string
    {
        $background = $backgrounds->active(CertificateBackground::TYPE_REVIEW_WORKSHEET);
        if (! str_starts_with($background->mime_type, 'image/')) {
            return null;
        }

        $contents = Storage::disk('local')->get($background->stored_file_path);

        return 'data:'.$background->mime_type.';base64,'.base64_encode($contents);
    }

    private function csvCell(mixed $value): string
    {
        $cell = str_replace(["\r", "\n"], ' ', (string) ($value ?? ''));

        return preg_match('/^[=+\-@]/', $cell) === 1 ? "'".$cell : $cell;
    }
}
