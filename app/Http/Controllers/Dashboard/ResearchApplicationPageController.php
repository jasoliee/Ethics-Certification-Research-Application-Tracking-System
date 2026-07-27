<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationRequirementService;
use App\Services\Applications\ApplicationSubmissionWindow;
use App\Services\Applications\ResearchApplicationSubmissionService;
use App\Support\DashboardNavigation;
use App\Support\RoleHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Presents authorized application details, requirements, and final submission.
 */
class ResearchApplicationPageController extends Controller
{
    /**
     * Display one authorized application with information and current requirement documents.
     */
    public function show(
        Request $request,
        ResearchApplication $researchApplication,
        ApplicationRequirementService $requirements,
        ApplicationSubmissionWindow $submissionWindow,
    ): View {
        // Apply the role-aware record policy before loading Applicant or Adviser detail data.
        Gate::authorize('view', $researchApplication);

        return view('dashboard.applications.show', [
            ...$this->pageData($request, $researchApplication, 'Application Details'),
            'requirementSummary' => $requirements->summary($researchApplication),
            'submissionWindow' => $submissionWindow->status(),
            'canEdit' => $request->user()->can('update', $researchApplication),
            'canSubmit' => $request->user()->can('submit', $researchApplication),
        ]);
    }

    /**
     * Display the applicant's upload checklist and final-submit controls.
     */
    public function requirements(
        Request $request,
        ResearchApplication $researchApplication,
        ApplicationRequirementService $requirements,
        ApplicationSubmissionWindow $submissionWindow,
    ): View {
        // Keep the upload checklist private to the owner even if its URL is guessed.
        Gate::authorize('view', $researchApplication);

        return view('dashboard.applications.requirements', [
            ...$this->pageData($request, $researchApplication, 'Document Submission'),
            'requirementSummary' => $requirements->summary($researchApplication),
            'submissionWindow' => $submissionWindow->status(),
            'canUpload' => $request->user()->can('upload', $researchApplication),
            'canSubmit' => $request->user()->can('submit', $researchApplication),
        ]);
    }

    /**
     * Execute the dedicated idempotent final-submission action.
     */
    public function submit(
        Request $request,
        ResearchApplication $researchApplication,
        ResearchApplicationSubmissionService $submissions,
    ): RedirectResponse {
        $submissions->submit($request->user(), $researchApplication);

        return redirect()->route('applicant.applications.show', $researchApplication)
            ->with('status', 'Application submitted to your adviser.');
    }

    /**
     * Build shared role-aware record details and breadcrumbs.
     *
     * @return array<string, mixed>
     */
    private function pageData(
        Request $request,
        ResearchApplication $application,
        string $title,
    ): array {
        $role = $request->user()->role;
        $breadcrumbs = [
            ['label' => 'Home', 'route' => RoleHome::routeNameFor($role)],
            ['label' => $role === UserRole::Reviewer ? 'Assignments' : 'Applications', 'route' => DashboardNavigation::applicationsRoute($role)],
        ];

        // Document Submission includes a linked Application Details breadcrumb for the Applicant.
        if ($title === 'Document Submission') {
            $breadcrumbs[] = [
                'label' => 'Application Details',
                'route' => 'applicant.applications.show',
                'parameters' => [$application],
            ];
        }

        $breadcrumbs[] = ['label' => $title];

        // Load only the identity fields needed by applicant and Adviser detail interfaces.
        return [
            'pageTitle' => $title,
            'application' => $application->loadMissing(
                'applicant:id,name,email,institutional_identifier,institution,department,program,role,applicant_type',
                'adviser:id,name,email,institution,department',
            ),
            'indexRoute' => DashboardNavigation::applicationsRoute($role),
            'role' => $role,
            'breadcrumbs' => $breadcrumbs,
        ];
    }
}
