<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $loginUsername = null;

        $status = Password::reset(
            $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $user, string $password) use (&$loginUsername): void {
                $isInitialSetup = ! $user->password_setup_completed_at;
                $loginUsername = $user->username;

                // Successful token use invalidates remembered sessions and records the credential-change time.
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'password_setup_completed_at' => $user->password_setup_completed_at ?? now(),
                    'account_status' => $user->account_status === AccountStatus::PendingSetup->value
                        ? AccountStatus::Active->value
                        : $user->account_status,
                    'remember_token' => Str::random(60),
                ])->save();

                $this->auditLog->record(null, $isInitialSetup ? 'user.password_setup_completed' : 'user.password_reset_completed', $user, [
                    'result' => 'completed',
                ]);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => trans($status)]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. You can now log in with your username.')
            ->with('login_username', $loginUsername);
    }
}
