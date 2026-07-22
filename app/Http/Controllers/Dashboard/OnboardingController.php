<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OnboardingController extends Controller
{
    public function __invoke(Request $request, AuditLogService $auditLog): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('completeOnboarding', $user);

        if (! $user->onboarding_completed_at) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
            $auditLog->record($user, 'user.onboarding_completed', $user, ['result' => 'completed']);
        }

        return response()->json(['completed' => true]);
    }
}
