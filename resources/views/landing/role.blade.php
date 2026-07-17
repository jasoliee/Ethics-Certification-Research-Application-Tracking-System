<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title }}</title>
</head>
<body style="margin: 0; min-height: 100vh; display: grid; place-items: center; background: #ffffff; color: #000000; font-family: Arial, Helvetica, sans-serif;">
    <main style="display: grid; justify-items: center; gap: 28px; padding: 24px;">
        <h1 style="margin: 0; text-align: center; font-size: 32px; line-height: 1.2; font-weight: 700;">
            {{ $title }}
        </h1>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="min-width: 112px; min-height: 44px; border: 0; border-radius: 6px; background: #198855; color: #ffffff; font: inherit; font-weight: 700; cursor: pointer;">
                Logout
            </button>
        </form>
    </main>
</body>
</html>
