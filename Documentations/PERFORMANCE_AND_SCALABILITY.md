# Performance and Scalability

## Implemented Measures

- Role dashboards use grouped status aggregates instead of one query per card.
- Display queries select only needed columns and eager-load required relations.
- Header notifications are limited to four; history is paginated.
- User lists use database filters and pagination.
- Bulk preflight batches existing email/identifier checks instead of querying for each row.
- Imports cap source size, row count, XLSX entry count, and expanded archive bytes.
- Mass setup resend iterates with `lazyById(50)` instead of loading all pending users at once.
- Vite builds versioned production assets and uses the reduced dashboard logo.

## Consistency and Concurrency

Account creation and initial submission use database transactions. Bulk confirmation atomically renames the actor-scoped preview file before database work so two requests cannot confirm the same preview. Unique database indexes remain the final defense against account identity collisions.

## Scaling Guidance

- Add indexes only from measured query plans.
- Move mail to a durable queue only with delivery-state reconciliation and supervised workers.
- Use private object storage for large document volume.
- Schedule cleanup of expired imports as a fallback to request-time cleanup.
- Keep authorization user-specific; do not cache private rendered pages.
- Paginate every growing audit, notification, application, and user collection.

See `PERFORMANCE_OPTIMIZATIONS.md` for the original dashboard query work.
