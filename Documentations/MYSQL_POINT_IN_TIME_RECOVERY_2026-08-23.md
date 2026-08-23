# MySQL point-in-time recovery — 2026-08-23

## Outcome

The local ECRATS MySQL database `ecrats_db` was successfully restored on 2026-08-23 to the last verified point immediately before the accidental PHPUnit `RefreshDatabase` drop transaction. Recovery was first performed in the isolated database `ecrats_recovery_20260823`, validated there, and only then imported into a newly recreated `ecrats_db` under the user's explicit restoration authorization.

The restored live database and isolated reconstruction have identical table names, row counts, and table checksums. No ECRATS migration, seeder, application test, build, feature implementation, or browser-validation pass was run as part of this recovery.

## Incident boundary and preserved evidence

- Source binary logs were located in `D:\laragon\data\mysql-8.4\` with binary logging enabled in row format.
- The first destructive transaction in `binlog.000006` begins with the anonymous GTID event at position `32686`; its destructive query event begins at position `32765` at 2026-08-23 17:35:25 Taipei time.
- The recovery stop position was therefore `32686`, excluding that transaction and all later events.
- MySQL was asked to rotate to `binlog.000007`, allowing a closed copy of `binlog.000006` to be preserved safely.
- The source and preserved closed-copy SHA-256 for `binlog.000006` are identical: `1D224B208EA1F4CA642263C5B5742A8C7035FA90E3E392913A972937ADA530A9` (251,534 bytes).
- Copies of `binlog.000001` through `binlog.000006`, the closed `binlog.000006`, both logical dumps, and the recovery helper are retained under `tmp/mysql-recovery-20260823/` and `tmp/mysql_recovery_admin.php`. They must not be deleted, changed, published, or replayed without explicit user direction.

## Isolated reconstruction

The database `ecrats_recovery_20260823` was created as a separate target. Copied binary logs were replayed with database-name rewriting from `ecrats_db` to the isolated target:

- `binlog.000002` from position `401`;
- `binlog.000003` through `binlog.000005` in full;
- the closed `binlog.000006` through stop position `32686`.

The replay did not target the damaged live database. The isolated reconstruction was validated before any live replacement.

## Isolated validation results

- 40 tables.
- 45 migration records, batches 1 through 6, including `2026_08_22_000000_add_submission_and_worksheet_settings`.
- 74 foreign-key constraints.
- Every table returned `OK` from `CHECK TABLE`.
- Tested foreign-key orphan counts were zero.
- Every referenced private artifact checked was present and its stored SHA-256 matched: four application documents, two certificate backgrounds, two review-form artifacts, and one certificate version.
- Representative preserved data included four users, one academic term, one certificate-released research application, four application documents, one reviewer assignment/submission/version, two review-form submissions/artifacts, one certificate recipient/certificate/version, 39 audit logs, and ten notifications.

Full restored row counts:

| Table | Rows |
| --- | ---: |
| academic_terms | 1 |
| applicant_survey_responses | 1 |
| application_certificate_recipients | 1 |
| application_code_sequences | 1 |
| application_decision_releases | 1 |
| application_documents | 4 |
| application_revision_requirements | 0 |
| application_revisions | 0 |
| application_screenings | 1 |
| audit_logs | 39 |
| cache | 22 |
| cache_locks | 0 |
| certificate_backgrounds | 2 |
| certificate_versions | 1 |
| certificates | 1 |
| deadline_configurations | 6 |
| document_requirements | 4 |
| endorsements | 1 |
| failed_jobs | 0 |
| job_batches | 0 |
| jobs | 0 |
| migrations | 45 |
| notifications | 10 |
| password_reset_tokens | 0 |
| profile_option_aliases | 0 |
| profile_options | 18 |
| research_applications | 1 |
| review_comment_status_changes | 0 |
| review_comments | 2 |
| review_form_artifacts | 2 |
| review_form_submissions | 2 |
| review_submission_versions | 1 |
| review_submissions | 1 |
| reviewer_assignments | 1 |
| reviewer_conflicts | 0 |
| reviewer_identity_reconciliations | 0 |
| sessions | 1 |
| timeline_calendar_events | 6 |
| users | 4 |
| workflow_drafts | 0 |

## Dumps and live restoration

Before replacing the damaged live schema, a logical dump of its post-incident state was retained:

- `tmp/mysql-recovery-20260823/damaged-ecrats_db-after-incident.sql`
- SHA-256: `EA9CBF3C92272906011BC701056DCBF21042AEDD476E53CABAA6D02BF7F79528`
- Size: 40,776 bytes.

The verified isolated reconstruction was exported to:

- `tmp/mysql-recovery-20260823/verified-ecrats-recovery-pre-drop.sql`
- SHA-256: `8CC866C88659B89C2037FB846E7375787A7589E66AFA6FB159D5331F549E40A0`
- Size: 145,997 bytes.

The verified dump contains no `CREATE DATABASE` or `USE` directive. Under the user's restoration authorization, the damaged `ecrats_db` schema was dropped and recreated, and this dump was imported into that exact target.

## Final live validation

- Live `ecrats_db` has the same 40 tables and 45 migration rows as `ecrats_recovery_20260823`.
- `php artisan migrate:status --no-ansi` reports every migration through 2026-08-22 as Ran in batches 1 through 6.
- Every live table returned `OK` from `CHECK TABLE`.
- Tested orphan counts remain zero.
- Referenced private-file existence and hash checks all pass.
- Exact live-to-recovery comparison: `same_table_names: true`, `same_row_counts: true`, `checksum_mismatches: []`.

## Retained fallback and safety warnings

- Keep `ecrats_recovery_20260823` until the user explicitly authorizes its removal. It is a verified isolated fallback, not an application database.
- Keep `tmp/mysql-recovery-20260823/`, `tmp/mysql_recovery_admin.php`, and `tmp/db_diagnostic.php` unchanged until the user explicitly authorizes cleanup.
- Never run automated tests against local MySQL. On this machine, `--env=testing` and `phpunit.xml` did not reliably isolate the connection.
- Before any future test process, explicitly set process-only `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, and an empty `DB_URL`; verify the resolved connection from inside that same process before allowing `RefreshDatabase`; run tests serially.
- Do not run `migrate:fresh`, `db:wipe`, destructive resets, broad rollbacks, truncation, or recovery replay against either live or recovery databases.
