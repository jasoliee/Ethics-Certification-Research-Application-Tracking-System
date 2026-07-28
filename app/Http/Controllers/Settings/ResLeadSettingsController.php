<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateDeadlineSettingsRequest;
use App\Http\Requests\Settings\UpdateOwnPasswordRequest;
use App\Http\Requests\Settings\UpdateOwnUsernameRequest;
use App\Services\Settings\DeadlineConfigurationService;
use App\Services\Settings\SelfAccountSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Presents and updates the RES Lead-only configuration workspace.
 */
class ResLeadSettingsController extends Controller
{
    public function index(
        Request $request,
        DeadlineConfigurationService $deadlines,
    ): View {
        $settings = $deadlines->settings();
        $semester = collect($settings)
            ->pluck('configuration.semester_label')
            ->filter()
            ->first();

        return view('settings.res-lead', [
            'pageTitle' => 'Settings',
            'settings' => $settings,
            'semesterLabel' => $semester,
            'settingsUser' => $request->user(),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'Settings'],
            ],
        ]);
    }

    public function updateDeadlines(
        UpdateDeadlineSettingsRequest $request,
        DeadlineConfigurationService $deadlines,
    ): RedirectResponse {
        $deadlines->update($request->user(), $request->validated());

        return back()->with('status', 'Semester deadlines and process availability were updated.');
    }

    public function updateUsername(
        UpdateOwnUsernameRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse {
        $settings->updateUsername($request->user(), $request->validated('username'));

        return back()->with('status', 'Your username was updated.');
    }

    public function updatePassword(
        UpdateOwnPasswordRequest $request,
        SelfAccountSettingsService $settings,
    ): RedirectResponse {
        $settings->updatePassword($request->user(), $request->validated('password'));

        return back()->with('status', 'Your password was changed securely.');
    }
}
