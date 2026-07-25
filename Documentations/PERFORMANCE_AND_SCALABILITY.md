# Performance and Scalability

## Implemented Measures

- Role dashboards use grouped status aggregates instead of one query per card.
- Display queries select only needed columns and eager-load required relations.
- Header notifications are limited to four; history is paginated.
- User lists use database filters and pagination.
- Bulk preflight batches existing email, institutional identifier, and generated-username checks instead of querying for each row.
- Profile-option usage totals are grouped per field rather than queried once per option row.
- Imports cap compressed size, account rows, columns, option rows, shared strings, archive entries, and expanded archive bytes before bounded XML processing.
- Mass setup resend iterates with `lazyById(50)` instead of loading all pending users at once.
- Vite builds versioned production assets and uses the reduced dashboard logo.

## Consistency and Concurrency

Account creation and initial submission use database transactions. Bulk confirmation atomically renames the actor-scoped preview file before database work so two requests cannot confirm the same preview. Each valid account then uses a short transaction; mail is sent only after database writes complete. Unique database indexes remain the final defense against account identity collisions.

## Scaling Guidance

- Add indexes only from measured query plans.
- Move mail to a durable queue only with delivery-state reconciliation and supervised workers.
- Use private object storage for large document volume.
- Schedule cleanup of expired imports as a fallback to request-time cleanup.
- Keep authorization user-specific; do not cache private rendered pages.
- Paginate every growing audit, notification, application, and user collection.

## Import Memory Boundary

The official workbook parser uses `ZipArchive` and bounded XML parsing without adding a general spreadsheet package. The 2 MB/250-row contract, 150-entry and 20 MB expanded-size limits, 1,000 option-row cap, 10,000 shared-string cap, exact column count, and parser-time maximum row references bound memory exposure. It is not a streaming parser and must not be expanded to arbitrary workbooks without a fresh performance and security review.

See `PERFORMANCE_OPTIMIZATIONS.md` for the original dashboard query work.
