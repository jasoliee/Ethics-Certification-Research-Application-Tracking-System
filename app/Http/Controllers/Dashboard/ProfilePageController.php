<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Applications\AdviserEndorsementStatisticsService;
use App\Services\Applications\ReviewerCapabilityProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfilePageController extends Controller
{
    public function __invoke(
        Request $request,
        AdviserEndorsementStatisticsService $endorsementStatistics,
        ReviewerCapabilityProfileService $reviewerCapabilities,
    ): View {
        $user = $request->user();

        return view('dashboard.profile', [
            'pageTitle' => 'Profile',
            'profileUser' => $user,
            'adviserStatistics' => $endorsementStatistics->for($user),
            'reviewerProfile' => $user->hasReviewerAccess() ? $reviewerCapabilities->for($user) : null,
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Profile'],
            ],
        ]);
    }
}
