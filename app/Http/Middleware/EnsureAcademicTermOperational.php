<?php

namespace App\Http\Middleware;

use App\Enums\AcademicTermStatus;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcademicTermOperational
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role === UserRole::ResLead) {
            return $next($request);
        }

        $pausedTerm = AcademicTerm::query()
            ->where('is_active', true)
            ->where('status', AcademicTermStatus::Paused->value)
            ->latest('updated_at')
            ->first();
        if (! $pausedTerm) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'ECRATS is temporarily paused by the Research Ethics Unit. No changes were accepted.',
                'academic_term' => $pausedTerm->label(),
            ], 423);
        }

        return response()->view('errors.term-paused', [
            'term' => $pausedTerm,
        ], 423, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
