<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateOwnEmailRequest;
use App\Http\Requests\Settings\UpdateOwnPasswordRequest;
use App\Http\Requests\Settings\UpdateOwnProfileRequest;
use App\Http\Requests\Settings\UpdateOwnUsernameRequest;
use App\Services\Applications\AdviserEndorsementStatisticsService;
use App\Services\Applications\ReviewerCapabilityProfileService;
use App\Services\Identity\ProfileOptionCatalog;
use App\Services\Settings\SelfAccountSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    public function index(
        Request $request,
        ProfileOptionCatalog $profileOptions,
        AdviserEndorsementStatisticsService $endorsementStatistics,
        ReviewerCapabilityProfileService $reviewerCapabilities,
    ): View {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Applicant, UserRole::Adviser], true), 403);

        return view('settings.account', [
            'pageTitle' => 'Settings',
            'settingsUser' => $user,
            'settingsRouteBase' => $user->role === UserRole::Applicant ? 'applicant.settings' : 'adviser.settings',
            'profileOptions' => $profileOptions->groupedForUser($user),
            'adviserStatistics' => $endorsementStatistics->for($user),
            'reviewerProfile' => $reviewerCapabilities->for($user),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Settings'],
            ],
        ]);
    }

    public function updateProfile(
        UpdateOwnProfileRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse {
        $settings->updateProfile($request->user(), $request->validated());

        return back()->with('status', 'Your profile information was updated.');
    }

    public function updateUsername(
        UpdateOwnUsernameRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse {
        $settings->updateUsername($request->user(), $request->validated('username'));

        return back()->with('status', 'Your username was updated.');
    }

    public function updateEmail(
        UpdateOwnEmailRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse {
        $settings->updateEmail($request->user(), $request->validated('email'), $request->session()->getId());
        $request->session()->regenerate();

        return back()->with('status', 'Your email address was updated and other signed-in sessions were revoked.');
    }

    public function updatePassword(
        UpdateOwnPasswordRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse|JsonResponse {
        $settings->updatePassword($request->user(), $request->validated('password'), $request->session()->getId());
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Your password was changed securely.']);
        }

        return back()->with('status', 'Your password was changed and other signed-in sessions were revoked.');
    }
}
