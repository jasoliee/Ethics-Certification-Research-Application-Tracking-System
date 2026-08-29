<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Temporarily Paused | ECRATS</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f3f7f5; color: #17202b; font: 16px/1.5 Arial, sans-serif; }
        main { width: min(600px, 100%); padding: 34px; border: 1px solid #c9ddd2; border-radius: 14px; background: #fff; box-shadow: 0 12px 32px rgba(8, 64, 39, .08); text-align: center; }
        .icon { display: grid; place-items: center; width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: #e7f4ed; color: #087241; font-size: 30px; }
        h1 { margin: 0 0 8px; font-size: 28px; }
        p { margin: 8px 0; color: #526071; }
        strong { color: #087241; }
        button { margin-top: 18px; min-height: 42px; padding: 9px 20px; border: 1px solid #087241; border-radius: 7px; background: #087241; color: #fff; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>
    <main role="main">
        <div class="icon" aria-hidden="true">⏸</div>
        <h1>ECRATS is temporarily paused</h1>
        <p>The Research Ethics Unit paused <strong>{{ $term->label() }}</strong> for maintenance or administrative review.</p>
        <p>Navigation and changes are locked. Please return after the REU Lead reactivates the term.</p>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign Out</button></form>
    </main>
</body>
</html>
