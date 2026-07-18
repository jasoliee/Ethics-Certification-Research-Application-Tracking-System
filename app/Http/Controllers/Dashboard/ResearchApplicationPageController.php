<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ResearchApplication;
use App\Support\DashboardNavigation;
use App\Support\RoleHome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResearchApplicationPageController extends Controller
{
    public function show(Request $request, ResearchApplication $researchApplication): View
    {
        Gate::authorize('view', $researchApplication);

        return $this->page($request, $researchApplication, 'Application Details', 'file-search');
    }

    public function requirements(Request $request, ResearchApplication $researchApplication): View
    {
        Gate::authorize('view', $researchApplication);

        return $this->page($request, $researchApplication, 'Submitted Requirements', 'clipboard');
    }

    private function page(Request $request, ResearchApplication $application, string $title, string $icon): View
    {
        $role = $request->user()->role;
        $breadcrumbs = [
            ['label' => 'Home', 'route' => RoleHome::routeNameFor($role)],
            ['label' => $role === UserRole::Reviewer ? 'Assignments' : 'Applications', 'route' => DashboardNavigation::applicationsRoute($role)],
        ];

        if ($title === 'Submitted Requirements') {
            $breadcrumbs[] = [
                'label' => 'Application Details',
                'route' => 'applicant.applications.show',
                'parameters' => [$application],
            ];
        }

        $breadcrumbs[] = ['label' => $title];

        return view('dashboard.application-page', [
            'pageTitle' => $title,
            'application' => $application->loadMissing('applicant:id,name', 'adviser:id,name'),
            'moduleIcon' => $icon,
            'indexRoute' => DashboardNavigation::applicationsRoute($role),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
