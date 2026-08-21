<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewFormArtifactStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\ReviewType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResLead\AssignApplicationReviewersRequest;
use App\Http\Requests\ResLead\ClassifyResearchApplicationRequest;
use App\Http\Requests\ResLead\UpdateResearchApplicationScreeningRequest;
use App\Models\ResearchApplication;
use App\Models\ReviewFormArtifact;
use App\Services\Applications\ApplicationRequirementService;
use App\Services\Applications\ResScreeningWorkflowService;
use App\Services\Applications\ReviewerEligibilityService;
use App\Services\Settings\AcademicTermResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lists, screens, classifies, and assigns adviser-endorsed applications for RES Leads.
 */
class ResLeadApplicationController extends Controller
{
    /**
     * Display the searchable and filterable RES applications queue.
     */
    public function index(Request $request, AcademicTermResolver $terms): View
    {
        $visibleStatuses = collect(ApplicationStatus::afterAdviserEndorsement());
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in($visibleStatuses->pluck('value')->all())],
            'review_type' => ['nullable', Rule::enum(ReviewType::class)],
            'affiliation' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')],
        ]);

        $applicationsQuery = $this->visibleApplicationsQuery($visibleStatuses->pluck('value')->all());
        $terms->applyFilters($applicationsQuery, $filters);

        // Applicant identity is deliberately excluded from this RES queue search boundary.
        $applicationsQuery
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['q']);

                $query->where(function (Builder $matching) use ($search): void {
                    $matching
                        ->where('application_code', 'like', "%{$search}%")
                        ->orWhere('research_title', 'like', "%{$search}%")
                        ->orWhere('research_category', 'like', "%{$search}%")
                        ->orWhere('institution', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('program', 'like', "%{$search}%")
                        ->orWhereHas('adviser', fn (Builder $advisers) => $advisers
                            ->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query
                ->where('application_status', $filters['status']))
            ->when(filled($filters['review_type'] ?? null), fn (Builder $query) => $query
                ->where('review_type', $filters['review_type']))
            ->when(filled($filters['affiliation'] ?? null), function (Builder $query) use ($filters): void {
                $affiliation = trim((string) $filters['affiliation']);

                $query->where(fn (Builder $matching) => $matching
                    ->where('institution', $affiliation)
                    ->orWhere('program', $affiliation));
            })
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query
                ->whereHas('endorsements', fn (Builder $endorsements) => $endorsements
                    ->whereDate('endorsed_at', '>=', $filters['date_from'])))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query
                ->whereHas('endorsements', fn (Builder $endorsements) => $endorsements
                    ->whereDate('endorsed_at', '<=', $filters['date_to'])));

        $applications = $applicationsQuery
            ->with([
                'adviser:id,name',
                // latestOfMany adds an internal join, so avoid an ambiguous unqualified relation projection.
                'latestEndorsement',
            ])
            ->latest('status_updated_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // Build one bounded affiliation option list from records already eligible for the RES queue.
        $affiliations = $this->visibleApplicationsQuery($visibleStatuses->pluck('value')->all())
            ->select(['institution', 'program'])
            ->distinct()
            ->get()
            ->flatMap(fn (ResearchApplication $application): array => [
                $application->institution,
                $application->program,
            ])
            ->filter()
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->sort()
            ->values();

        return view('dashboard.applications.res-index', [
            'pageTitle' => 'Applications',
            'applications' => $applications,
            'statuses' => $visibleStatuses,
            'reviewTypes' => ReviewType::cases(),
            'affiliations' => $affiliations,
            'filters' => $filters,
            'termOptions' => $terms->filterOptions(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Applications'],
            ],
        ]);
    }

    /**
     * Display screening details, classification state, documents, and assigned reviewers.
     */
    public function show(
        Request $request,
        ResearchApplication $researchApplication,
        ApplicationRequirementService $requirements,
    ): View {
        Gate::authorize('view', $researchApplication);
        $application = $this->loadResApplication($researchApplication);
        $canUpdateScreening = $request->user()->can('updateScreening', $application);
        $officialReviewArtifacts = ReviewFormArtifact::query()
            ->where('status', ReviewFormArtifactStatus::Ready->value)
            ->whereHas('formSubmission.assignment', fn (Builder $assignments) => $assignments
                ->where('research_application_id', $application->id)
                ->whereHas('reviewSubmission', fn (Builder $submissions) => $submissions
                    ->where('status', ReviewSubmissionStatus::Submitted->value)))
            ->with([
                'formSubmission.assignment.reviewer:id,name',
                'formSubmission.assignment.reviewSubmission',
            ])
            ->latest('generated_at')
            ->get();

        return view('dashboard.applications.res-show', [
            'pageTitle' => 'Application Screening Details',
            'application' => $application,
            'officialReviewArtifacts' => $officialReviewArtifacts,
            'requirementSummary' => $requirements->summary($application),
            'canClassify' => $request->user()->can('classify', $application),
            // Edit mode is explicit so saved details remain the default read-only presentation.
            'canUpdateScreening' => $canUpdateScreening,
            'editingScreening' => $canUpdateScreening && $request->boolean('edit_screening'),
            'canAssignReviewers' => $request->user()->can('assignReviewers', $application),
            'reviewTypes' => ReviewType::cases(),
            'breadcrumbs' => [
                ['label' => 'Applications', 'route' => 'res.applications.index'],
                ['label' => $application->application_code],
            ],
        ]);
    }

    /**
     * Persist one validated classification and route reviewer-based cases to assignment.
     */
    public function classify(
        ClassifyResearchApplicationRequest $request,
        ResearchApplication $researchApplication,
        ResScreeningWorkflowService $workflow,
    ): RedirectResponse {
        $screening = $workflow->classify(
            $request->user(),
            $researchApplication,
            $request->validated(),
        );

        if ($screening->review_type->requiresReviewers()) {
            return redirect()
                ->route('res.applications.reviewers.index', $researchApplication)
                ->with('status', 'Classification saved. Select the required eligible reviewer or reviewers.');
        }

        return redirect()
            ->route('res.applications.show', $researchApplication)
            ->with('status', 'Application classified as exempted. Reviewer assignment was bypassed.');
    }

    /**
     * Apply an authorized screening correction and route the RES Lead to the resulting workflow state.
     */
    public function updateScreening(
        UpdateResearchApplicationScreeningRequest $request,
        ResearchApplication $researchApplication,
        ResScreeningWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->updateScreening(
            $request->user(),
            $researchApplication,
            $request->validated(),
        );
        $researchApplication->refresh();

        if ($researchApplication->application_status === ApplicationStatus::AwaitingReviewerAssignment) {
            return redirect()
                ->route('res.applications.reviewers.index', $researchApplication)
                ->with('status', 'Screening updated. Select the reviewer set required by the corrected classification.');
        }

        return redirect()
            ->route('res.applications.show', $researchApplication)
            ->with('status', 'Screening details and classification decision updated.');
    }

    /**
     * Display eligible reviewers or the immutable assignment result for this application.
     */
    public function reviewers(
        Request $request,
        ResearchApplication $researchApplication,
        ReviewerEligibilityService $eligibility,
    ): View {
        Gate::authorize('view', $researchApplication);
        $filters = $request->validate([
            'reviewer_q' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
        ]);
        $application = $this->loadResApplication($researchApplication);
        $reviewType = ReviewType::tryFrom((string) $application->review_type);

        abort_unless($application->screening && $reviewType?->requiresReviewers(), 404);
        $canAssign = $request->user()->can('assignReviewers', $application);

        return view('dashboard.applications.res-reviewers', [
            'pageTitle' => 'Reviewer Assignment',
            'application' => $application,
            'reviewType' => $reviewType,
            'requiredReviewerCount' => $reviewType->reviewerCount(),
            'canAssign' => $canAssign,
            'candidates' => $canAssign
                ? $eligibility->paginateCandidates($application, $reviewType, $filters)
                : null,
            // Department options use the same active/classification boundary as the candidate list.
            'departments' => $canAssign
                ? $eligibility->departmentOptions($application, $reviewType)
                : collect(),
            'filters' => $filters,
            'breadcrumbs' => [
                ['label' => 'Applications', 'route' => 'res.applications.index'],
                ['label' => $application->application_code, 'route' => 'res.applications.show', 'parameters' => [$application]],
                ['label' => 'Reviewer Assignment'],
            ],
        ]);
    }

    /**
     * Persist the exact eligible reviewer set after server-side confirmation.
     */
    public function assignReviewers(
        AssignApplicationReviewersRequest $request,
        ResearchApplication $researchApplication,
        ResScreeningWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->assignReviewers(
            $request->user(),
            $researchApplication,
            $request->validated('reviewer_ids'),
            $request->validated()['reassignment_reason'] ?? null,
        );

        return redirect()
            ->route('res.applications.reviewers.index', $researchApplication)
            ->with('status', 'Reviewers successfully assigned.');
    }

    /**
     * Start every queue query at the formal post-endorsement visibility boundary.
     *
     * @param  array<int, string>  $statusValues
     */
    private function visibleApplicationsQuery(array $statusValues): Builder
    {
        return ResearchApplication::query()
            ->select([
                'id',
                'application_code',
                'applicant_user_id',
                'adviser_user_id',
                'applicant_type',
                'research_title',
                'research_type',
                'research_category',
                'institution',
                'department',
                'program',
                'review_type',
                'application_status',
                'submitted_at',
                'status_updated_at',
            ])
            ->whereNotNull('submitted_at')
            ->whereIn('application_status', $statusValues);
    }

    /**
     * Eager-load only authorized screening, document, and reviewer display relationships.
     */
    private function loadResApplication(ResearchApplication $application): ResearchApplication
    {
        $reviewCycle = max(0, ((int) $application->current_revision_cycle) - 1);
        $assignmentReviewType = $reviewCycle === 0 ? 'initial_review' : 'revision_review';

        return $application->loadMissing([
            'applicant:id,name,email,institutional_identifier,institution,department,program,role,applicant_type',
            'adviser:id,name,email,institution,department',
            'latestEndorsement.adviser:id,name',
            'screening.screenedBy:id,name',
            'reviewerAssignments' => fn ($assignments) => $assignments
                ->current()
                ->where('review_type', $assignmentReviewType)
                ->where('review_cycle', $reviewCycle)
                ->orderBy('assigned_at')
                ->with(['reviewer' => fn ($reviewers) => $reviewers
                    ->withCount(['reviewerAssignments as active_assignment_count' => fn (Builder $active) => $active
                        ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())])]),
        ]);
    }
}
