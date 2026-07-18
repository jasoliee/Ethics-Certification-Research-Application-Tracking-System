<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ReviewerAssignment;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReviewerAssignmentPageController extends Controller
{
    public function __invoke(ReviewerAssignment $reviewerAssignment): View
    {
        Gate::authorize('view', $reviewerAssignment);

        return view('dashboard.assignment-page', [
            'pageTitle' => 'Assignment Details',
            'assignment' => $reviewerAssignment->loadMissing('researchApplication'),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Assignments', 'route' => 'reviewer.assignments.index'],
                ['label' => 'Assignment Details'],
            ],
        ]);
    }
}
