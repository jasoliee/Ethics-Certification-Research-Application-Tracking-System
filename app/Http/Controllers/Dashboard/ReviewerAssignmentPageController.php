<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ResearchType;
use App\Enums\ReviewCommentCategory;
use App\Enums\ReviewCommentScope;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewFormType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ReviewerAssignment;
use App\Services\Settings\DeadlineProcessAvailability;
use App\Support\ReviewFormCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewerAssignmentPageController extends Controller
{
    /**
     * List only assignments owned by the authenticated Reviewer.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'review_cycle' => ['nullable', Rule::in(['initial_review', 'revision_review'])],
            'status' => ['nullable', Rule::enum(ReviewerAssignmentStatus::class)],
            'research_type' => ['nullable', Rule::enum(ResearchType::class)],
            'deadline' => ['nullable', Rule::in(['due_soon', 'overdue', 'no_deadline'])],
        ]);

        $assignments = ReviewerAssignment::query()
            ->where('reviewer_user_id', $request->user()->id)
            ->with(['researchApplication:id,application_code,research_title,research_type,review_type'])
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['q']);

                $query->whereHas('researchApplication', fn (Builder $applications) => $applications
                    ->where(fn (Builder $matching) => $matching
                        ->where('application_code', 'like', "%{$search}%")
                        ->orWhere('research_title', 'like', "%{$search}%")));
            })
            ->when(filled($filters['review_cycle'] ?? null), fn (Builder $query) => $query
                ->where('review_type', $filters['review_cycle']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query
                ->where('assignment_status', $filters['status']))
            ->when(filled($filters['research_type'] ?? null), fn (Builder $query) => $query
                ->whereHas('researchApplication', fn (Builder $applications) => $applications
                    ->where('research_type', $filters['research_type'])))
            ->when(($filters['deadline'] ?? null) === 'due_soon', fn (Builder $query) => $query
                ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())
                ->whereBetween('review_deadline_at', [now(), now()->addDays(7)]))
            ->when(($filters['deadline'] ?? null) === 'overdue', fn (Builder $query) => $query
                ->whereIn('assignment_status', ReviewerAssignmentStatus::activeValues())
                ->whereNotNull('review_deadline_at')
                ->where('review_deadline_at', '<', now()))
            ->when(($filters['deadline'] ?? null) === 'no_deadline', fn (Builder $query) => $query
                ->whereNull('review_deadline_at'))
            ->latest('assigned_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.assignments.index', [
            'pageTitle' => 'Assigned Applications',
            'assignments' => $assignments,
            'filters' => $filters,
            'statuses' => ReviewerAssignmentStatus::cases(),
            'researchTypes' => ResearchType::cases(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Assigned Applications'],
            ],
        ]);
    }

    /**
     * Show one policy-authorized assignment without loading applicant or Adviser identity.
     */
    public function show(
        ReviewerAssignment $reviewerAssignment,
        DeadlineProcessAvailability $deadlines,
    ): View {
        Gate::authorize('view', $reviewerAssignment);
        $reviewerAssignment->load([
            'researchApplication' => fn ($applications) => $applications->select([
                'id',
                'application_code',
                'research_title',
                'research_type',
                'research_category',
                'target_participants',
                'expected_duration',
                'expected_start_date',
                'expected_end_date',
                'abstract',
                'review_type',
            ]),
            'reviewSubmission',
            'formSubmissions',
        ]);

        $canOpenWorkspace = Gate::allows('openWorkspace', $reviewerAssignment);

        if ($canOpenWorkspace) {
            $reviewerAssignment->researchApplication->load([
                'documents' => fn ($documents) => $documents
                    ->where('is_current', true)
                    ->with('requirement:id,name')
                    ->orderBy('document_requirement_id'),
            ]);
        }

        return view('dashboard.assignments.show', [
            'pageTitle' => 'Assigned Application',
            'assignment' => $reviewerAssignment,
            'canOpenWorkspace' => $canOpenWorkspace,
            'reviewWindow' => $deadlines->status(
                'reviewer-submission',
                UserRole::Reviewer,
                'Reviewer submission',
            ),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Assignments', 'route' => 'reviewer.assignments.index'],
                ['label' => $reviewerAssignment->researchApplication->application_code],
            ],
        ]);
    }

    /**
     * Open the assignment-owned blind workspace without loading Applicant or Adviser identity.
     */
    public function workspace(
        ReviewerAssignment $reviewerAssignment,
        DeadlineProcessAvailability $deadlines,
    ): View {
        Gate::authorize('openWorkspace', $reviewerAssignment);
        $reviewerAssignment->load([
            'researchApplication' => fn ($applications) => $applications->select([
                'id',
                'application_code',
                'research_title',
                'research_type',
                'research_category',
                'target_participants',
                'expected_duration',
                'expected_start_date',
                'expected_end_date',
                'abstract',
                'review_type',
            ]),
            'researchApplication.documents' => fn ($documents) => $documents
                ->where('is_current', true)
                ->with('requirement:id,name')
                ->orderBy('document_requirement_id'),
            'reviewSubmission',
            'formSubmissions',
            'comments' => fn ($comments) => $comments
                ->with('document:id,original_file_name')
                ->latest(),
        ]);

        $reviewWindow = $deadlines->status(
            'reviewer-submission',
            UserRole::Reviewer,
            'Reviewer submission',
        );
        $canWrite = Gate::allows('work', $reviewerAssignment) && $reviewWindow['open'];
        $forms = $reviewerAssignment->formSubmissions
            ->keyBy(fn ($form): string => $form->form_type->value);
        $formCatalog = collect(ReviewFormType::cases())->mapWithKeys(
            fn (ReviewFormType $type): array => [$type->value => [
                'type' => $type,
                'questions' => ReviewFormCatalog::questions($type),
                'answers' => ReviewFormCatalog::answers($type),
            ]],
        );

        return view('dashboard.assignments.workspace', [
            'pageTitle' => 'Review Workspace',
            'assignment' => $reviewerAssignment,
            'reviewWindow' => $reviewWindow,
            'canWrite' => $canWrite,
            'forms' => $forms,
            'formCatalog' => $formCatalog,
            'decisions' => ReviewDecision::cases(),
            'commentScopes' => ReviewCommentScope::cases(),
            'commentCategories' => ReviewCommentCategory::cases(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Assignments', 'route' => 'reviewer.assignments.index'],
                ['label' => $reviewerAssignment->researchApplication->application_code, 'route' => 'reviewer.assignments.show', 'parameters' => [$reviewerAssignment]],
                ['label' => 'Review Workspace'],
            ],
        ]);
    }
}
