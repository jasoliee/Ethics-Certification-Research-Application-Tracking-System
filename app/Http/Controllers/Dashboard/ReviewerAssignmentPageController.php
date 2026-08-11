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
use App\Models\ApplicationDocument;
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
    private const COMMENT_PAGE_SIZE = 20;

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
            'tab' => ['nullable', Rule::in(['assigned', 'revision', 'completed'])],
        ]);

        $assignments = ReviewerAssignment::query()
            ->current()
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
            ->when(($filters['tab'] ?? null) === 'assigned', fn (Builder $query) => $query
                ->whereIn('assignment_status', [ReviewerAssignmentStatus::Pending->value, ReviewerAssignmentStatus::InReview->value]))
            ->when(($filters['tab'] ?? null) === 'revision', fn (Builder $query) => $query
                ->where('assignment_status', ReviewerAssignmentStatus::RevisionReview->value))
            ->when(($filters['tab'] ?? null) === 'completed', fn (Builder $query) => $query
                ->where('assignment_status', ReviewerAssignmentStatus::DecisionSubmitted->value))
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
            'pageTitle' => $request->routeIs('reviewer.reviews.index') ? 'Review Tasks' : 'Assigned Applications',
            'reviewTasksPage' => $request->routeIs('reviewer.reviews.index'),
            'assignments' => $assignments,
            'filters' => $filters,
            'statuses' => ReviewerAssignmentStatus::cases(),
            'researchTypes' => ResearchType::cases(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => $request->routeIs('reviewer.reviews.index') ? 'Review Tasks' : 'Assigned Applications'],
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
                'submitted_at',
            ]),
            'reviewSubmission',
            'formSubmissions.artifact',
        ]);

        $canOpenWorkspace = Gate::allows('openWorkspace', $reviewerAssignment);

        if ($canOpenWorkspace) {
            $completedCycle = $reviewerAssignment->assignment_status === ReviewerAssignmentStatus::DecisionSubmitted;
            $maximumVersion = ((int) $reviewerAssignment->review_cycle) + 1;
            $reviewerAssignment->researchApplication->load([
                'documents' => fn ($documents) => $documents
                    ->when(
                        $completedCycle,
                        fn ($query) => $query->where('document_version', '<=', $maximumVersion),
                        fn ($query) => $query->where('is_current', true),
                    )
                    ->with('requirement:id,name')
                    ->orderBy('document_requirement_id')
                    ->orderByDesc('document_version')
                    ->orderByDesc('id'),
            ]);
        }

        return view('dashboard.assignments.show', [
            'pageTitle' => 'Assigned Application',
            'assignment' => $reviewerAssignment,
            'canOpenWorkspace' => $canOpenWorkspace,
            'reviewWindow' => $deadlines->status(
                $this->deadlineKey($reviewerAssignment),
                UserRole::Reviewer,
                $this->deadlineLabel($reviewerAssignment),
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
        $completedCycle = $reviewerAssignment->assignment_status === ReviewerAssignmentStatus::DecisionSubmitted;
        $maximumVersion = ((int) $reviewerAssignment->review_cycle) + 1;
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
                'submitted_at',
            ]),
            'researchApplication.documents' => fn ($documents) => $documents
                ->when(
                    $completedCycle,
                    fn ($query) => $query->where('document_version', '<=', $maximumVersion),
                    fn ($query) => $query->where('is_current', true),
                )
                ->with('requirement:id,name')
                ->orderBy('document_requirement_id')
                ->orderByDesc('document_version')
                ->orderByDesc('id'),
            'reviewSubmission',
            'formSubmissions.artifact',
        ]);

        $reviewWindow = $deadlines->status(
            $this->deadlineKey($reviewerAssignment),
            UserRole::Reviewer,
            $this->deadlineLabel($reviewerAssignment),
        );
        $canWrite = Gate::allows('work', $reviewerAssignment) && $reviewWindow['open'];
        $commentTotal = $reviewerAssignment->comments()->count();
        $commentBatch = $reviewerAssignment->comments()
            ->with('document:id,original_file_name')
            ->latest('id')
            ->limit(self::COMMENT_PAGE_SIZE + 1)
            ->get();
        $commentsHaveOlder = $commentBatch->count() > self::COMMENT_PAGE_SIZE;
        $comments = $commentBatch->take(self::COMMENT_PAGE_SIZE)->values();
        $forms = $reviewerAssignment->formSubmissions
            ->keyBy(fn ($form): string => $form->form_type->value);
        $formCatalog = collect(ReviewFormType::cases())->mapWithKeys(
            fn (ReviewFormType $type): array => [$type->value => [
                'type' => $type,
                'items' => ReviewFormCatalog::items($type),
                'questions' => ReviewFormCatalog::questions($type),
                'answers' => ReviewFormCatalog::answers($type),
            ]],
        );
        $historicalDocuments = collect();
        $historicalReviews = collect();

        if ((int) $reviewerAssignment->review_cycle > 0) {
            $historicalDocuments = ApplicationDocument::query()
                ->where('research_application_id', $reviewerAssignment->research_application_id)
                ->where('document_version', '<=', $reviewerAssignment->review_cycle)
                ->with('requirement:id,name')
                ->orderByDesc('document_version')
                ->orderByDesc('uploaded_at')
                ->limit(100)
                ->get();
            $historicalReviews = ReviewerAssignment::query()
                ->current()
                ->where('research_application_id', $reviewerAssignment->research_application_id)
                ->where('reviewer_user_id', $reviewerAssignment->reviewer_user_id)
                ->where('review_cycle', '<', $reviewerAssignment->review_cycle)
                ->with([
                    'reviewSubmission:id,reviewer_assignment_id,status,decision,submitted_at',
                    'comments' => fn ($comments) => $comments
                        ->with('document.requirement:id,name')
                        ->orderBy('created_at')
                        ->orderBy('id'),
                ])
                ->orderByDesc('review_cycle')
                ->limit(10)
                ->get();
        }

        return view('dashboard.assignments.workspace', [
            'pageTitle' => 'Review Workspace',
            'assignment' => $reviewerAssignment,
            'reviewWindow' => $reviewWindow,
            'canWrite' => $canWrite,
            'comments' => $comments,
            'commentTotal' => $commentTotal,
            'commentsHaveOlder' => $commentsHaveOlder,
            'commentsNextBeforeId' => $commentsHaveOlder ? $comments->last()?->id : null,
            'forms' => $forms,
            'formCatalog' => $formCatalog,
            'decisions' => ReviewDecision::cases(),
            // Historical page-scoped comments remain readable, while new comments use the
            // streamlined overall/document choices required by the current workspace.
            'commentScopes' => collect(ReviewCommentScope::cases())
                ->reject(fn (ReviewCommentScope $scope): bool => $scope === ReviewCommentScope::Page)
                ->values(),
            'commentCategories' => ReviewCommentCategory::cases(),
            'historicalDocuments' => $historicalDocuments,
            'historicalReviews' => $historicalReviews,
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Assignments', 'route' => 'reviewer.assignments.index'],
                ['label' => $reviewerAssignment->researchApplication->application_code, 'route' => 'reviewer.assignments.show', 'parameters' => [$reviewerAssignment]],
                ['label' => 'Review Workspace'],
            ],
        ]);
    }

    private function deadlineKey(ReviewerAssignment $assignment): string
    {
        return $assignment->review_type === 'revision_review' || (int) $assignment->review_cycle > 0
            ? 'reviewing-revision-period'
            : 'reviewer-submission';
    }

    private function deadlineLabel(ReviewerAssignment $assignment): string
    {
        return $this->deadlineKey($assignment) === 'reviewing-revision-period'
            ? 'Reviewing of revision'
            : 'Reviewer submission';
    }
}
