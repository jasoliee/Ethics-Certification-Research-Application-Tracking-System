<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>ECRATS Login</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ecrats-login-body">
    @php
        $hasCredentialError = $errors->has('credentials');
        $usernameIsInvalid = $hasCredentialError || $errors->has('username');
        $passwordIsInvalid = $hasCredentialError || $errors->has('password');
    @endphp

    <main class="login-page" aria-labelledby="login-title">
        <section class="login-shell" aria-label="ECRATS secure login">
            <div class="login-panel login-panel-brand">
                <div class="login-brand-content">
                    <div class="login-logo" role="img" aria-label="Kolehiyo ng Lungsod ng Dasmarinas seal"></div>
                    <h1>Ethics Review Section<br>Submission Portal</h1>
                    <p>Secure access for students, advisers, reviewers, and the Research Ethics Standing Committee.</p>
                    <small>&copy; 2026 Kolehiyo ng Lungsod ng Dasmarinas</small>
                </div>
            </div>

            <div class="login-panel login-panel-form">
                <div class="login-form-content">
                    <h2 id="login-title">Login Account</h2>
                    <p class="login-instruction">Enter your username and password below to access your account.</p>

                    <form class="login-form" method="POST" action="{{ route('login.store') }}" aria-label="Login form" data-login-form novalidate>
                        @csrf

                        <div class="login-field">
                            <label for="username">Username</label>
                            <input
                                id="username"
                                name="username"
                                type="text"
                                value="{{ old('username') }}"
                                autocomplete="username"
                                placeholder="Enter your username"
                                aria-describedby="login-validation-messages"
                                aria-invalid="{{ $usernameIsInvalid ? 'true' : 'false' }}"
                                required
                                maxlength="30"
                                autofocus
                            >
                        </div>

                        <div class="login-field">
                            <label for="password">Password</label>
                            <div class="password-input-wrapper">
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                    aria-describedby="login-validation-messages"
                                    aria-invalid="{{ $passwordIsInvalid ? 'true' : 'false' }}"
                                    required
                                    minlength="8"
                                    maxlength="16"
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    aria-label="Show password"
                                    aria-controls="password"
                                    aria-pressed="false"
                                    title="Show password"
                                    data-password-toggle
                                    hidden
                                >
                                    <svg class="password-toggle-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24">
                                        <path d="M2.1 12s3.6-7 9.9-7 9.9 7 9.9 7-3.6 7-9.9 7-9.9-7-9.9-7Z" />
                                        <circle cx="12" cy="12" r="3" />
                                        <path class="password-toggle-slash" data-password-state-indicator d="m3 3 18 18" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div id="login-validation-messages" class="login-validation-messages" role="alert" aria-live="polite" aria-atomic="true">
                            @if ($hasCredentialError)
                                <span class="login-validation-message" data-login-error data-error-for="credentials">{{ $errors->first('credentials') }}</span>
                            @else
                                @error('username')
                                    <span class="login-validation-message" data-login-error data-error-for="username">{{ $message }}</span>
                                @enderror
                                @error('password')
                                    <span class="login-validation-message" data-login-error data-error-for="password">{{ $message }}</span>
                                @enderror
                            @endif
                        </div>

                        <button class="login-submit" type="submit">Login</button>
                    </form>

                    <div class="login-divider" aria-hidden="true">
                        <span>OR</span>
                    </div>

                    <p id="administrator-contact" class="login-help">
                        Do not have an account?
                        <a href="https://kld.edu.ph/office_of_the_vice_president_for_research_mission_and_external_affairs.php" target="_blank" rel="noopener noreferrer">Contact your administrator</a>
                    </p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
