<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Reports\ApplicantSurveyReportService;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResReportController extends Controller
{
    public function index(Request $request, ApplicantSurveyReportService $surveyReports): View
    {
        abort_unless($request->user()->role === UserRole::ResLead, 403);

        return view('dashboard.reports.res-index', [
            'pageTitle' => 'Reports',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Reports'],
            ],
            'surveySummary' => $surveyReports->summary(),
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
}
