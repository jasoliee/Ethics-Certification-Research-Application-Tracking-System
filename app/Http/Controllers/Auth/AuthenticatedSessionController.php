<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogService;
use App\Support\RoleHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route(RoleHome::routeNameFor($request->user()->role));
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route(RoleHome::routeNameFor($request->user()->role));
        }

        $request->ensureIsNotRateLimited();

        $credentials = [
            'username' => $request->validated('username'),
            'password' => $request->validated('password'),
            'account_status' => 'active',
        ];

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($request->throttleKey());

            if (RateLimiter::attempts($request->throttleKey()) >= 2) {
                $this->auditLog->record(null, 'auth.login_failed', metadata: [
                    'attempts' => RateLimiter::attempts($request->throttleKey()),
                    'username_hash' => hash('sha256', strtolower($request->validated('username'))),
                    'result' => 'failed',
                ]);
            }

            return back()->withErrors([
                'credentials' => 'The username or password is incorrect.',
            ])->onlyInput('username');
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();
        $this->auditLog->record($request->user(), 'auth.login_succeeded', $request->user(), [
            'result' => 'succeeded',
        ]);

        return redirect()->route(RoleHome::routeNameFor($request->user()->role));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
