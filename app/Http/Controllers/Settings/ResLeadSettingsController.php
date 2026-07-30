<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateDeadlineSettingsRequest;
use App\Http\Requests\Settings\UpdateOwnPasswordRequest;
use App\Http\Requests\Settings\UpdateOwnUsernameRequest;
use App\Services\Settings\AcademicTermResolver;
use App\Services\Settings\DeadlineConfigurationService;
use App\Services\Settings\SelfAccountSettingsService;
use Illuminate\Http\JsonResponse;
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
        AcademicTermResolver $terms,
    ): View {
        $configuredTerm = $terms->latestConfigured();
        $currentTerm = $terms->current();
        $settings = $deadlines->settings($configuredTerm);
        $upcomingDeadline = collect($settings)
            ->filter(fn (array $process): bool => $process['configuration']?->due_at?->isFuture() === true)
            ->sortBy(fn (array $process): int => $process['configuration']->due_at->timestamp)
            ->first();

        return view('settings.res-lead', [
            'pageTitle' => 'Settings',
            'settings' => $settings,
            'configuredTerm' => $configuredTerm,
            'activeTermLabel' => $currentTerm?->label() ?? AcademicTermResolver::FALLBACK_LABEL,
            'upcomingDeadline' => $upcomingDeadline,
            'minimumDate' => now()->toDateString(),
            'minimumDeadline' => now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i'),
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

        return back()->with('status', 'Semester, academic year, deadlines, and process availability were updated.');
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
    ): RedirectResponse|JsonResponse {
        $settings->updatePassword($request->user(), $request->validated('password'));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your password was changed securely.',
            ]);
        }

        return back()->with('status', 'Your password was changed securely.');
    }
}
