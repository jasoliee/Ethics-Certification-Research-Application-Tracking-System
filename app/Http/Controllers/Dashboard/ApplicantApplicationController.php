<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\ResearchType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StoreResearchApplicationRequest;
use App\Http\Requests\Applications\UpdateResearchApplicationRequest;
use App\Models\ResearchApplication;
use App\Services\Applications\ApplicationInformationService;
use App\Services\Applications\ApplicationSubmissionLimit;
use App\Services\Applications\ApplicationSubmissionWindow;
use App\Services\Applications\ResearchApplicationDraftService;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Presents and persists the applicant-owned application-information workflow.
 */
class ApplicantApplicationController extends Controller
{
    /**
     * List only applications belonging to the authenticated applicant.
     */
    public function index(
        Request $request,
        AcademicTermResolver $terms,
        ApplicationSubmissionLimit $submissionLimit,
        ApplicationSubmissionWindow $submissionWindow,
    ): View {
        $filters = validator($request->query(), [
            'semester' => ['nullable', 'string', 'max:50'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ])->validate();

        // Paginate applicant-owned records and eager load Adviser identity for a bounded list query.
        $applicationsQuery = ResearchApplication::query()
            ->select([
                'id',
                'application_code',
                'applicant_user_id',
                'adviser_user_id',
                'research_title',
                'research_type',
                'application_status',
                'current_stage',
                'submitted_at',
                'updated_at',
            ])
            ->where('applicant_user_id', $request->user()->id)
            ->where('application_status', '!=', ApplicationStatus::Archived->value);
        $terms->applyFilters($applicationsQuery, $filters);
        $applications = $applicationsQuery
            ->with('adviser:id,name')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();
        $editableApplication = $this->editableApplication($request);
        $limitStatus = $submissionLimit->status($request->user());

        return view('dashboard.applications.applicant-index', [
            'pageTitle' => 'Application',
            'applications' => $applications,
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'editableApplication' => $editableApplication,
            'submissionLimit' => $limitStatus,
            'submissionWindow' => $submissionWindow->status(),
            'canStartApplication' => $editableApplication !== null || ! $limitStatus['reached'],
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Application'],
            ],
        ]);
    }

    /**
     * Display a new form or continue the authenticated applicant's editable draft.
     */
    public function create(
        Request $request,
        ApplicationInformationService $information,
        ApplicationSubmissionLimit $submissionLimit,
    ): View|RedirectResponse {
        $application = $this->editableApplication($request);

        // Existing corrections remain reachable after the limit because they do not consume another slot.
        if ($application) {
            Gate::authorize('update', $application);
        } else {
            Gate::authorize('create', ResearchApplication::class);

            if (! $submissionLimit->canCreate($request->user())) {
                return redirect()
                    ->route('applicant.applications.index')
                    ->withErrors(['application_limit' => ApplicationSubmissionLimit::REACHED_MESSAGE]);
            }
        }

        return $this->form($request, $information, $application);
    }

    /**
     * Create or update the applicant's single draft from validated information.
     */
    public function store(
        StoreResearchApplicationRequest $request,
        ResearchApplicationDraftService $drafts,
    ): RedirectResponse {
        $application = $drafts->save($request->user(), $request->validated());

        return redirect()
            ->route('applicant.applications.requirements', $application)
            ->with('status', 'Application information saved. Complete the required document checklist.');
    }

    /**
     * Display an eligible applicant-owned draft for editing.
     */
    public function edit(
        Request $request,
        ResearchApplication $researchApplication,
        ApplicationInformationService $information,
    ): View {
        // Reject non-owners and records outside the editable application-state boundary.
        Gate::authorize('update', $researchApplication);

        return $this->form($request, $information, $researchApplication);
    }

    /**
     * Update the route-bound eligible draft and continue to document submission.
     */
    public function update(
        UpdateResearchApplicationRequest $request,
        ResearchApplication $researchApplication,
        ResearchApplicationDraftService $drafts,
    ): RedirectResponse {
        $application = $drafts->save(
            $request->user(),
            $request->validated(),
            $researchApplication,
        );

        return redirect()
            ->route('applicant.applications.requirements', $application)
            ->with('status', 'Application information updated.');
    }

    /**
     * Permanently discard one owned unsubmitted draft and its private files.
     */
    public function destroy(
        Request $request,
        ResearchApplication $researchApplication,
        ResearchApplicationDraftService $drafts,
    ): RedirectResponse {
        $drafts->discard($request->user(), $researchApplication);

        return redirect()
            ->route('applicant.applications.index')
            ->with('status', 'Draft application discarded.');
    }

    /**
     * Assemble one shared high-fidelity form payload for create and edit routes.
     */
    private function form(
        Request $request,
        ApplicationInformationService $information,
        ?ResearchApplication $application,
    ): View {
        return view('dashboard.applications.form', [
            'pageTitle' => $application ? 'Edit Application' : 'Create Application',
            'application' => $application,
            'researchTypes' => ResearchType::cases(),
            'profileOptions' => $information->profileOptions($request->user(), $application),
            'advisers' => $information->advisers($request->user()),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Application', 'route' => 'applicant.applications.index'],
                ['label' => $application ? 'Edit Application' : 'Create Application'],
            ],
        ]);
    }

    /**
     * Prefer the database draft slot, then the newest Adviser-returned initial submission.
     */
    private function editableApplication(Request $request): ?ResearchApplication
    {
        $application = ResearchApplication::query()
            ->where('draft_owner_user_id', $request->user()->id)
            ->first();

        return $application ?? ResearchApplication::query()
            ->where('applicant_user_id', $request->user()->id)
            ->where('application_status', ApplicationStatus::ReturnedByAdviser->value)
            ->latest('updated_at')
            ->first();
    }
}
