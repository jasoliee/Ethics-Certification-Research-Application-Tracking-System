# Development Workflow

Use the repository as the shared source of truth for planning, implementation, review, and verification. Keep work small enough to review, and document larger changes before editing code.

## Planning Defaults

- Use `PLANS.md` for large or risky work.
- Keep each plan tied to a requirement source, module, and verification path.
- Record unresolved source conflicts in the plan or the relevant documentation file.
- Do not implement disputed behavior until the team agrees on the expected outcome.

## Editor Defaults

Use Visual Studio Code for:

- Reading nearby source files before editing.
- Keeping selected files and modules in focus.
- Implementing approved narrow changes.
- Reviewing local diffs before commit.
- Running focused checks from the repository root.

Recommended behavior:

- Keep changes scoped to the current branch and task.
- Review the diff before staging.
- Avoid committing generated, private, or environment-specific files.
- Check changed files for conflict markers before pushing.

## Review Workflow

Use pull requests for shared review. Each PR should include:

- What changed.
- Why it changed.
- Requirement or document reference.
- Commands and tests run.
- Database or environment impact.
- Security or privacy impact.

Do not merge changes with unresolved conflict markers, failing checks, exposed secrets, unreviewed migrations, or unclear authorization behavior.

## Local Actions

Run commands from the repository root:

```powershell
composer validate --strict
composer check-platform-reqs
php artisan route:list
php artisan migrate:status
php artisan test
npm.cmd run build
```

Do not add package-install or destructive database actions as shortcuts.
