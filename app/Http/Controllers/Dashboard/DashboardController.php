<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardDataService $dashboard,
        AcademicTermResolver $terms,
    ): View {
        $user = $request->user();
        $filters = $request->validate([
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')],
        ]);
        $academicTermId = filled($filters['academic_term_id'] ?? null)
            ? (int) $filters['academic_term_id']
            : null;

        // The canonical dashboard URL resolves the authenticated user's role-specific data and view.
        [$view, $data] = match ($user->role) {
            UserRole::Applicant => ['dashboard.roles.applicant', $dashboard->applicant($user, $academicTermId)],
            UserRole::Adviser => ['dashboard.roles.adviser', $dashboard->adviser($user, $academicTermId)],
            UserRole::Reviewer => ['dashboard.roles.reviewer', $dashboard->reviewer($user, $academicTermId)],
            UserRole::ResLead => ['dashboard.roles.res-lead', $dashboard->resLead($academicTermId)],
        };

        return view($view, [
            ...$data,
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'pageTitle' => 'Dashboard',
            'breadcrumbs' => [],
        ]);
    }

    /** Render the review-capability dashboard without changing the Adviser's primary home. */
    public function reviewer(
        Request $request,
        DashboardDataService $dashboard,
        AcademicTermResolver $terms,
    ): View {
        $filters = $request->validate([
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')],
        ]);
        $academicTermId = filled($filters['academic_term_id'] ?? null)
            ? (int) $filters['academic_term_id']
            : null;

        return view('dashboard.roles.reviewer', [
            ...$dashboard->reviewer($request->user(), $academicTermId),
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'pageTitle' => 'Reviewer Dashboard',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Reviewer Dashboard'],
            ],
        ]);
    }
}
