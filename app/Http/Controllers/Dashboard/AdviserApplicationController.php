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
 * Lists only formally submitted applications assigned to the authenticated Research Adviser.
 */
class AdviserApplicationController extends Controller
{
    /**
     * Apply search, status, and date filters to one paginated Adviser-owned query.
     */
    public function index(
        Request $request,
        AcademicTermResolver $terms,
    ): View {
        // Limit status input to states that can appear in the formally submitted Adviser workspace.
        $visibleStatuses = collect(ApplicationStatus::cases())
            ->reject(fn (ApplicationStatus $status): bool => in_array($status, [
                ApplicationStatus::Draft,
                ApplicationStatus::Incomplete,
                ApplicationStatus::Archived,
            ], true));
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in($visibleStatuses->pluck('value')->all())],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')->where('is_active', true)],
        ]);
        $selectedStatus = $visibleStatuses->firstWhere('value', $filters['status'] ?? null);

        // Exclude drafts and other Advisers before applying user-entered filters.
        $applicationsQuery = ResearchApplication::query()
            ->select([
                'id',
                'application_code',
                'applicant_user_id',
                'adviser_user_id',
                'research_title',
                'research_type',
                'program',
                'application_status',
                'current_stage',
                'submitted_at',
            ])
            ->where('adviser_user_id', $request->user()->id)
            ->whereNotNull('submitted_at')
            ->whereNotIn('application_status', [
                ApplicationStatus::Draft->value,
                ApplicationStatus::Incomplete->value,
                ApplicationStatus::Archived->value,
            ]);
        $terms->applyFilters($applicationsQuery, $filters);
        $applications = $applicationsQuery
            ->with('applicant:id,name,institutional_identifier,program')
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['q']);

                // Search safe application and applicant identity fields in one scoped query.
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('application_code', 'like', "%{$search}%")
                        ->orWhere('research_title', 'like', "%{$search}%")
                        ->orWhere('program', 'like', "%{$search}%")
                        ->orWhereHas('applicant', fn (Builder $applicantQuery) => $applicantQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('institutional_identifier', 'like', "%{$search}%")
                            ->orWhere('program', 'like', "%{$search}%"));
                });
            })
            ->when($selectedStatus, fn (Builder $query) => $query
                ->where('application_status', $selectedStatus->value))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query
                ->whereDate('submitted_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query
                ->whereDate('submitted_at', '<=', $filters['date_to']))
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.applications.adviser-index', [
            'pageTitle' => 'Submitted Applications',
            'applications' => $applications,
            'statuses' => $visibleStatuses,
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Submitted Applications'],
            ],
        ]);
    }
}
