<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardDataService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardDataService $dashboard): View
    {
        $user = $request->user();

        // The canonical dashboard URL resolves the authenticated user's role-specific data and view.
        [$view, $data] = match ($user->role) {
            UserRole::Applicant => ['dashboard.roles.applicant', $dashboard->applicant($user)],
            UserRole::Adviser => ['dashboard.roles.adviser', $dashboard->adviser($user)],
            UserRole::Reviewer => ['dashboard.roles.reviewer', $dashboard->reviewer($user)],
            UserRole::ResLead => ['dashboard.roles.res-lead', $dashboard->resLead()],
        };

        return view($view, [
            ...$data,
            'pageTitle' => 'Dashboard',
            'breadcrumbs' => [],
        ]);
    }

    /** Render the review-capability dashboard without changing the Adviser's primary home. */
    public function reviewer(Request $request, DashboardDataService $dashboard): View
    {
        return view('dashboard.roles.reviewer', [
            ...$dashboard->reviewer($request->user()),
            'pageTitle' => 'Reviewer Dashboard',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Reviewer Dashboard'],
            ],
        ]);
    }
}
