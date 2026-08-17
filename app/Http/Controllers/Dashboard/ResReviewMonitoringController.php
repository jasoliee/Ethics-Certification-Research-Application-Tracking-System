<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewType;
use App\Http\Controllers\Controller;
use App\Services\Applications\ResReviewMonitoringQueryService;
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
    ): View {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'review_type' => ['nullable', Rule::in([
                ReviewType::Expedited->value,
                ReviewType::FullBoard->value,
            ])],
            'assignment_status' => ['nullable', Rule::in(collect(ReviewerAssignmentStatus::cases())
                ->reject(fn (ReviewerAssignmentStatus $status): bool => $status === ReviewerAssignmentStatus::Superseded)
                ->pluck('value')
                ->all())],
            'deadline' => ['nullable', Rule::in(['overdue', 'due_soon', 'on_track', 'no_deadline'])],
            'consensus' => ['nullable', Rule::enum(ReviewConsensusStatus::class)],
            'adviser_q' => ['nullable', 'string', 'max:150'],
            'adviser_department' => ['nullable', 'string', 'max:150'],
            'adviser_workload' => ['nullable', Rule::in([
                'awaiting_action',
                'remaining_expected',
                'not_received',
                'target_met',
                'no_target',
            ])],
        ]);
        $filters['q'] = trim((string) ($filters['q'] ?? ''));
        $filters['adviser_q'] = trim((string) ($filters['adviser_q'] ?? ''));

        return view('dashboard.reviews.res-monitoring', [
            'pageTitle' => 'Review Monitoring',
            'filters' => $filters,
            'reviewTypes' => [ReviewType::Expedited, ReviewType::FullBoard],
            'assignmentStatuses' => collect(ReviewerAssignmentStatus::cases())
                ->reject(fn (ReviewerAssignmentStatus $status): bool => $status === ReviewerAssignmentStatus::Superseded),
            'consensusStatuses' => ReviewConsensusStatus::cases(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Review Monitoring'],
            ],
            ...$monitoring->dashboard($filters),
        ]);
    }
}
