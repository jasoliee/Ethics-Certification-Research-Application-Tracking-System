<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\Applications\ResReviewMonitoringQueryService;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Displays the RES-only reviewer operations monitor.
 */
class ResReviewMonitoringController extends Controller
{
    public function __invoke(
        Request $request,
        ResReviewMonitoringQueryService $monitoring,
        AcademicTermResolver $terms,
    ): View {
        $filters = $request->validate([
            'reviewer_q' => ['nullable', 'string', 'max:150'],
            'reviewer_department' => ['nullable', 'string', 'max:150'],
            'reviewer_institution' => ['nullable', 'string', 'max:150'],
            'adviser_q' => ['nullable', 'string', 'max:150'],
            'adviser_department' => ['nullable', 'string', 'max:150'],
            'adviser_institution' => ['nullable', 'string', 'max:150'],
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')],
        ]);
        $filters['reviewer_q'] = trim((string) ($filters['reviewer_q'] ?? ''));
        $filters['adviser_q'] = trim((string) ($filters['adviser_q'] ?? ''));

        return view('dashboard.reviews.res-monitoring', [
            'pageTitle' => 'Review Monitoring',
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Review Monitoring'],
            ],
            ...$monitoring->dashboard($filters),
        ]);
    }

    public function reviewerAssignments(
        Request $request,
        User $reviewer,
        AcademicTermResolver $terms,
    ): View {
        abort_unless($request->user()?->role === UserRole::ResLead, 403);
        abort_unless($reviewer->role === UserRole::Adviser, 404);
        $filters = $request->validate([
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')],
            'review_type' => ['nullable', Rule::in(['expedited', 'full_board', 'revision_review'])],
            'assignment_status' => ['nullable', Rule::enum(ReviewerAssignmentStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $assignments = $reviewer->reviewerAssignments()
            ->whereNull('superseded_at')
            ->with(['researchApplication' => fn ($applications) => $applications
                ->select([
                    'id',
                    'academic_term_id',
                    'application_code',
                    'research_title',
                    'application_status',
                    'review_type',
                ])
                ->with('academicTerm:id,semester,academic_year')])
            ->when(filled($filters['academic_term_id'] ?? null), fn (Builder $assignments) => $assignments
                ->whereHas('researchApplication', fn (Builder $applications) => $applications
                    ->where('academic_term_id', (int) $filters['academic_term_id'])))
            ->when(filled($filters['review_type'] ?? null), fn (Builder $query) => $query->where('review_type', $filters['review_type']))
            ->when(filled($filters['assignment_status'] ?? null), fn (Builder $query) => $query->where('assignment_status', $filters['assignment_status']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('assigned_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('assigned_at', '<=', $filters['date_to']))
            ->latest('assigned_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.reviews.res-reviewer-assignments', [
            'pageTitle' => 'Reviewer Assignments',
            'reviewer' => $reviewer,
            'assignments' => $assignments,
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Review Monitoring', 'route' => 'res.review-monitoring.index'],
                ['label' => $reviewer->name],
            ],
        ]);
    }

    public function adviserApplications(
        Request $request,
        User $adviser,
        AcademicTermResolver $terms,
    ): View {
        abort_unless($request->user()?->role === UserRole::ResLead, 403);
        abort_unless($adviser->role === UserRole::Adviser, 404);
        $filters = $request->validate([
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')],
            'review_type' => ['nullable', Rule::in(['expedited', 'full_board'])],
            'application_status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $applications = ResearchApplication::query()
            ->select([
                'id',
                'academic_term_id',
                'application_code',
                'research_title',
                'application_status',
                'review_type',
                'status_updated_at',
            ])
            ->whereHas('endorsements', fn (Builder $endorsements) => $endorsements
                ->where('adviser_user_id', $adviser->id)
                ->where('endorsement_status', EndorsementStatus::Endorsed->value))
            ->when(filled($filters['academic_term_id'] ?? null), fn (Builder $query) => $query
                ->where('academic_term_id', (int) $filters['academic_term_id']))
            ->when(filled($filters['review_type'] ?? null), fn (Builder $query) => $query->where('review_type', $filters['review_type']))
            ->when(filled($filters['application_status'] ?? null), fn (Builder $query) => $query->where('application_status', $filters['application_status']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('status_updated_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('status_updated_at', '<=', $filters['date_to']))
            ->with('academicTerm:id,semester,academic_year')
            ->latest('status_updated_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.reviews.res-adviser-applications', [
            'pageTitle' => 'Endorsed Applications',
            'adviser' => $adviser,
            'applications' => $applications,
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Review Monitoring', 'route' => 'res.review-monitoring.index'],
                ['label' => $adviser->name],
            ],
        ]);
    }
}
