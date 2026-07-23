<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password | ECRATS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ecrats-login-body">
    <main class="reset-page" aria-labelledby="reset-title">
        {{-- The reset token and email are validated by Laravel's password broker before any credential changes. --}}
        <section class="reset-card">
            <img src="{{ Vite::asset('assets/logo-256.png') }}" alt="Kolehiyo ng Lungsod ng Dasmarinas seal">
            <div><h1 id="reset-title">Set a New Password</h1><p>Enter a new password for your ECRATS account.</p></div>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ old('email', $email) }}">

                <div class="reset-field"><label for="reset-email">Email Address</label><input id="reset-email" type="email" value="{{ old('email', $email) }}" disabled></div>
                <div class="reset-field">
                    <label for="reset-password">New Password</label>
                    <div class="password-input-wrapper">
                        <input id="reset-password" name="password" type="password" minlength="8" maxlength="64" autocomplete="new-password" required autofocus>
                        <button type="button" class="password-toggle" aria-label="Show password" aria-controls="reset-password" aria-pressed="false" title="Show password" data-password-toggle hidden>
                            <svg class="password-toggle-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                                <path d="M2.1 12s3.6-7 9.9-7 9.9 7 9.9 7-3.6 7-9.9 7-9.9-7-9.9-7Z" />
                                <circle cx="12" cy="12" r="3" />
                                <path class="password-toggle-slash" data-password-state-indicator d="m3 3 18 18" />
                            </svg>
                        </button>
                    </div>
                    @error('password')<span>{{ $message }}</span>@enderror
                </div>
                <div class="reset-field">
                    <label for="reset-password-confirmation">Confirm Password</label>
                    <div class="password-input-wrapper">
                        <input id="reset-password-confirmation" name="password_confirmation" type="password" maxlength="64" autocomplete="new-password" required>
                        <button type="button" class="password-toggle" aria-label="Show password" aria-controls="reset-password-confirmation" aria-pressed="false" title="Show password" data-password-toggle hidden>
                            <svg class="password-toggle-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                                <path d="M2.1 12s3.6-7 9.9-7 9.9 7 9.9 7-3.6 7-9.9 7-9.9-7-9.9-7Z" />
                                <circle cx="12" cy="12" r="3" />
                                <path class="password-toggle-slash" data-password-state-indicator d="m3 3 18 18" />
                            </svg>
                        </button>
                    </div>
                </div>
                @error('email')<div class="reset-error" role="alert">{{ $message }}</div>@enderror
                <button type="submit">Reset Password</button>
            </form>
            <a href="{{ route('login') }}">Return to login</a>
        </section>
    </main>
</body>
</html>
