# Performance Optimizations

## Problems Found

- Adviser and RES dashboards executed one count query for each status card.
- Reviewer status cards also used separate per-status queries.
- The full notification page loaded a fixed collection instead of paginating long histories.
- Dashboard queries selected full rows in places where only display fields were needed.
- The 1024-pixel logo transferred about 1.65 MB even though it rendered at roughly 150 pixels.
- During workstation testing, an unattended verbose PHP development-server stream could block and produce misleading multi-second or minute-long requests.

## Changes Applied

- Status cards now use one grouped aggregate query per role dashboard.
- Required relationships remain eagerly loaded, with selected columns limited to those used by the view.
- Recent dashboard collections retain a five-record limit.
- Notification history uses 20-record pagination; the header loads only four records.
- Database access remains outside Blade views.
- A visually equivalent 256-pixel logo is used by authenticated and login layouts. Its asset size is about 80 KB; the original master remains available.
- Local validation uses `php artisan serve --quiet --no-reload` to prevent request-log backpressure.

## Verification

Feature coverage asserts a maximum of eight database queries for every empty role dashboard, including shared notification context. The Vite production bundle succeeds and contains the reduced logo asset. A local uncached login GET completed in approximately 0.64 seconds during this run; local hardware and debug settings will affect that number.

No user-specific page output is cached. Authorization and current-user scoping remain evaluated on every request.
