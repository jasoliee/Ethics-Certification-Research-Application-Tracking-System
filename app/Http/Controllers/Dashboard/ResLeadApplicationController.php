<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\ResearchApplication;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lists formally endorsed applications that have entered the RES workflow.
 */
class ResLeadApplicationController extends Controller
{
    public function index(Request $request, AcademicTermResolver $terms): View
    {
        $visibleStatuses = collect(ApplicationStatus::afterAdviserEndorsement());
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in($visibleStatuses->pluck('value')->all())],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'semester' => ['nullable', 'string', 'max:50'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);
        $selectedStatus = $visibleStatuses->first(
            fn (ApplicationStatus $status): bool => $status->value === ($filters['status'] ?? null),
        );

        $applicationsQuery = ResearchApplication::query()
            ->select([
                'id',
                'academic_term_id',
                'application_code',
                'applicant_user_id',
                'adviser_user_id',
                'research_title',
                'review_type',
                'application_status',
                'submitted_at',
                'status_updated_at',
            ])
            ->whereNotNull('submitted_at')
            ->whereIn('application_status', $visibleStatuses->pluck('value'));

        $terms->applyFilters($applicationsQuery, $filters);
        $applications = $applicationsQuery
            ->with([
                'applicant:id,name,institutional_identifier',
                'adviser:id,name',
                'latestEndorsement',
            ])
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['q']);

                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('application_code', 'like', "%{$search}%")
                        ->orWhere('research_title', 'like', "%{$search}%")
                        ->orWhereHas('applicant', fn (Builder $applicantQuery) => $applicantQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('institutional_identifier', 'like', "%{$search}%"))
                        ->orWhereHas('adviser', fn (Builder $adviserQuery) => $adviserQuery
                            ->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($selectedStatus, fn (Builder $query) => $query
                ->where('application_status', $selectedStatus->value))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query
                ->whereHas('endorsements', fn (Builder $endorsements) => $endorsements
                    ->whereDate('endorsed_at', '>=', $filters['date_from'])))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query
                ->whereHas('endorsements', fn (Builder $endorsements) => $endorsements
                    ->whereDate('endorsed_at', '<=', $filters['date_to'])))
            ->latest('status_updated_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.applications.res-index', [
            'pageTitle' => 'Endorsed Applications',
            'applications' => $applications,
            'statuses' => $visibleStatuses,
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Endorsed Applications'],
            ],
        ]);
    }
}
