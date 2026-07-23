<?php

namespace App\Http\Controllers\Identity;

use App\Enums\AccountStatus;
use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\ReviewerClassification;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangeManagedUserStatusRequest;
use App\Http\Requests\Identity\ConfirmManagedUserImportRequest;
use App\Http\Requests\Identity\ImportManagedUsersRequest;
use App\Http\Requests\Identity\MassManagedUserActionRequest;
use App\Http\Requests\Identity\RegenerateManagedUsernameRequest;
use App\Http\Requests\Identity\StoreManagedUserRequest;
use App\Http\Requests\Identity\StoreProfileOptionRequest;
use App\Http\Requests\Identity\UpdateManagedUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Identity\AccountTypeCatalog;
use App\Services\Identity\ManagedPasswordResetService;
use App\Services\Identity\ManagedUserMassActionService;
use App\Services\Identity\ProfileOptionCatalog;
use App\Services\Identity\SafeSpreadsheet;
use App\Services\Identity\UserAccountService;
use App\Services\Identity\UserBulkImportService;
use App\Services\Identity\UserManagementQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserManagementQueryService $queries,
        private readonly AccountTypeCatalog $accountTypes,
        private readonly ProfileOptionCatalog $profileOptions,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'applicant_type' => ['nullable', Rule::enum(ApplicantType::class)],
            'account_status' => ['nullable', Rule::enum(AccountStatus::class)],
            'institution' => ['nullable', 'string', 'max:150'],
        ])->validate();
        $visible = $this->queries->visibleTo($request->user());
        $counts = $this->managementCounts(clone $visible);
        $institutions = collect($this->profileOptions->values(ProfileOptionField::Institution))
            ->merge((clone $visible)
                ->whereNotNull('institution')
                ->where('institution', '!=', '')
                ->distinct()
                ->orderBy('institution')
                ->pluck('institution'))
            ->unique()
            ->sort()
            ->values();

        if (filled($filters['institution'] ?? null)) {
            $visible->where('institution', $filters['institution']);
        }

        // The listing selects only fields required by the table and paginates every filtered result set.
        $users = $this->queries->applyFilters($visible, $filters)
            ->select([
                'id',
                'name',
                'email',
                'institutional_identifier',
                'role',
                'applicant_type',
                'account_status',
                'institution',
                'department',
                'created_at',
            ])
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('identity.users.index', [
            'pageTitle' => 'User Management',
            'users' => $users,
            'filters' => $filters,
            'counts' => $counts,
            'institutions' => $institutions,
            'routeBase' => $this->routeBase($request->user()),
            'isResLead' => $request->user()->role === UserRole::ResLead,
            'canManageProfileOptions' => $request->user()->can('manageProfileOptions', User::class),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management'],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);
        $types = $this->accountTypes->allowedFor($request->user());
        $selectedType = $request->query('mode') === 'individual'
            ? collect($types)->firstWhere('key', (string) $request->query('account_type'))
            : null;

        return view('identity.users.create', [
            'pageTitle' => 'Add New User',
            'accountTypes' => $types,
            'selectedType' => $selectedType,
            'profileOptions' => $this->profileOptions->grouped(),
            'canManageProfileOptions' => $request->user()->can('manageProfileOptions', User::class),
            'routeBase' => $this->routeBase($request->user()),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management', 'route' => $this->routeBase($request->user()).'.index'],
                ['label' => 'Add New User'],
            ],
        ]);
    }

    public function store(
        StoreManagedUserRequest $request,
        UserAccountService $accounts,
        ManagedPasswordResetService $passwordResets,
    ): RedirectResponse {
        $user = $accounts->create($request->user(), $request->validated());
        $emailSent = $passwordResets->sendForCreatedAccount($request->user(), $user);

        return redirect()
            ->route($this->routeBase($request->user()).'.show', ['managedUser' => $user, 'created' => 1])
            ->with('status', $emailSent
                ? 'Account created and the setup email was sent.'
                : 'Account created, but the setup email could not be sent. You can resend it from the account profile.')
            ->with('setup_delivery_status', $emailSent ? 'sent' : 'failed');
    }

    public function show(Request $request, User $managedUser): View
    {
        Gate::authorize('view', $managedUser);

        return view('identity.users.show', [
            'pageTitle' => 'User Management',
            'managedUser' => $managedUser,
            'metrics' => $this->accountMetrics($managedUser),
            'routeBase' => $this->routeBase($request->user()),
            'wasCreated' => $request->boolean('created'),
            'canChangeStatus' => $request->user()->can('changeStatus', $managedUser),
            'canResetPassword' => $request->user()->can('initiatePasswordReset', $managedUser),
            'setupDeliveryStatus' => session('setup_delivery_status', $managedUser->setup_email_status),
            'breadcrumbs' => [
                ['label' => 'User Management', 'route' => $this->routeBase($request->user()).'.index'],
                ['label' => $managedUser->name],
            ],
        ]);
    }

    public function edit(Request $request, User $managedUser): View
    {
        Gate::authorize('update', $managedUser);

        return view('identity.users.edit', [
            'pageTitle' => 'Edit User',
            'managedUser' => $managedUser,
            'profileOptions' => $this->profileOptions->groupedForUser($managedUser),
            'canManageProfileOptions' => $request->user()->can('manageProfileOptions', User::class),
            'routeBase' => $this->routeBase($request->user()),
            'breadcrumbs' => [
                ['label' => 'User Management', 'route' => $this->routeBase($request->user()).'.index'],
                ['label' => $managedUser->name, 'route' => $this->routeBase($request->user()).'.show', 'parameters' => ['managedUser' => $managedUser]],
                ['label' => 'Edit'],
            ],
        ]);
    }

    public function update(
        UpdateManagedUserRequest $request,
        User $managedUser,
        UserAccountService $accounts,
    ): RedirectResponse {
        $accounts->updateProfile($request->user(), $managedUser, $request->validated());

        return redirect()
            ->route($this->routeBase($request->user()).'.show', $managedUser)
            ->with('status', 'Account information updated.');
    }

    public function storeProfileOption(
        StoreProfileOptionRequest $request,
        ProfileOptionCatalog $profileOptions,
    ): RedirectResponse {
        $field = ProfileOptionField::from($request->validated('option_field'));
        $profileOptions->create($request->user(), $field, $request->validated('option_value'));

        return back()->with('status', "{$field->label()} option added.");
    }

    public function changeStatus(
        ChangeManagedUserStatusRequest $request,
        User $managedUser,
        UserAccountService $accounts,
    ): RedirectResponse {
        $accounts->changeStatus($request->user(), $managedUser, $request->validated('account_status'));

        return back()->with('status', 'Account status updated.');
    }

    public function regenerateUsername(
        RegenerateManagedUsernameRequest $request,
        User $managedUser,
        UserAccountService $accounts,
    ): RedirectResponse {
        $accounts->regenerateUsername($request->user(), $managedUser, $request->validated());

        return redirect()
            ->route($this->routeBase($request->user()).'.show', $managedUser)
            ->with('status', 'Identity corrected and the generated username was updated.');
    }

    public function sendPasswordReset(
        Request $request,
        User $managedUser,
        ManagedPasswordResetService $passwordResets,
    ): RedirectResponse {
        $passwordResets->send($request->user(), $managedUser);

        return back()->with('status', 'A secure password reset link was sent to the account email.');
    }

    public function importForm(Request $request): View|RedirectResponse
    {
        Gate::authorize('import', User::class);
        $accountType = (string) $request->query('account_type');

        if ($accountType === '') {
            return redirect()->route($this->routeBase($request->user()).'.create');
        }

        $selectedType = $this->accountTypes->authorized($request->user(), $accountType);

        return view('identity.users.import', [
            'pageTitle' => 'Bulk Account Import',
            'routeBase' => $this->routeBase($request->user()),
            'selectedType' => $selectedType,
            'preview' => null,
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management', 'route' => $this->routeBase($request->user()).'.index'],
                ['label' => 'Bulk Account Import'],
            ],
        ]);
    }

    public function import(ImportManagedUsersRequest $request, UserBulkImportService $imports): View
    {
        $preview = $imports->preview(
            $request->user(),
            $request->file('accounts_file'),
            $request->validated('account_type'),
        );

        return view('identity.users.import', [
            'pageTitle' => 'Bulk Account Import',
            'routeBase' => $this->routeBase($request->user()),
            'selectedType' => $preview['account_type'],
            'preview' => $preview,
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management', 'route' => $this->routeBase($request->user()).'.index'],
                ['label' => 'Bulk Account Import'],
            ],
        ]);
    }

    public function confirmImport(
        ConfirmManagedUserImportRequest $request,
        UserBulkImportService $imports,
    ): RedirectResponse {
        $result = $imports->confirm($request->user(), $request->validated('import_token'));

        return redirect()
            ->route($this->routeBase($request->user()).'.index')
            ->with('status', "{$result['created']} accounts created; {$result['skipped']} duplicates or existing accounts skipped. {$result['emails_sent']} setup emails sent; {$result['emails_failed']} failed.");
    }

    public function template(Request $request, string $format, SafeSpreadsheet $spreadsheets): StreamedResponse|BinaryFileResponse
    {
        Gate::authorize('import', User::class);
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);
        $type = $this->accountTypes->authorized($request->user(), (string) $request->query('account_type'));
        $headers = $type['template_headers'];
        $filename = 'ecrats-'.$type['key'].'-template.'.$format;
        $optionValues = $this->profileOptions->grouped();
        $dropdowns = [
            'year_level' => $optionValues[ProfileOptionField::YearLevel->value],
            'institution' => $optionValues[ProfileOptionField::Institution->value],
            'department' => $optionValues[ProfileOptionField::Department->value],
            'program' => $optionValues[ProfileOptionField::Program->value],
            'reviewer_classification' => collect(ReviewerClassification::cases())->map->label()->all(),
        ];
        $dropdowns = collect($dropdowns)->only($headers)->all();

        if ($format === 'xlsx') {
            return response()
                ->download($spreadsheets->createTemplate($headers, $type['example_row'], $dropdowns), $filename, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'no-store, private',
                ])
                ->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($headers, $type): void {
            $stream = fopen('php://output', 'wb');

            if ($stream !== false) {
                fputcsv($stream, $headers, ',', '"', '\\');
                fputcsv($stream, collect($headers)->map(fn (string $header): string => (string) ($type['example_row'][$header] ?? ''))->all(), ',', '"', '\\');
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function errorReport(Request $request, string $token, UserBulkImportService $imports): StreamedResponse
    {
        Gate::authorize('import', User::class);
        $errors = $imports->errorReport($request->user(), $token);

        return response()->streamDownload(function () use ($errors): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['row', 'errors'], ',', '"', '\\');

            foreach ($errors as $error) {
                $message = implode(' | ', $error['errors']);
                $safeMessage = preg_match('/^[=+\-@]/', $message) === 1 ? "'".$message : $message;
                fputcsv($stream, [$error['row'], $safeMessage], ',', '"', '\\');
            }

            fclose($stream);
        }, 'ecrats-account-import-errors.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function massAction(
        MassManagedUserActionRequest $request,
        ManagedUserMassActionService $massActions,
    ): RedirectResponse {
        $result = $massActions->execute(
            $request->user(),
            $request->validated('action'),
            $request->validated('user_ids', []),
        );
        $message = in_array($request->validated('action'), ['resend_setup', 'resend_all_pending'], true)
            ? "{$result['sent']} setup emails sent; {$result['failed']} failed."
            : "{$result['affected']} accounts updated.";

        return back()->with('status', $message);
    }

    public function auditIndex(Request $request): View
    {
        Gate::authorize('viewAuditLog', User::class);
        $filters = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'result' => ['nullable', 'string', 'max:100'],
        ])->validate();
        $hiddenActions = ['user.onboarding_completed', 'user.password_setup_completed'];
        $search = trim((string) ($filters['search'] ?? ''));
        $baseQuery = AuditLog::query()->whereNotIn('action', $hiddenActions);
        $actions = (clone $baseQuery)->distinct()->orderBy('action')->pluck('action');
        $results = (clone $baseQuery)
            ->whereNotNull('metadata')
            ->pluck('metadata')
            ->pluck('result')
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $logs = AuditLog::query()
            ->select(['id', 'actor_user_id', 'action', 'subject_type', 'subject_id', 'metadata', 'created_at'])
            ->with('actor:id,name,username,role')
            ->whereNotIn('action', $hiddenActions)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($matches) use ($search): void {
                    $matches
                        ->whereLike('action', '%'.$search.'%')
                        ->orWhereHas('actor', function ($actors) use ($search): void {
                            $actors
                                ->whereLike('name', '%'.$search.'%')
                                ->orWhereLike('username', '%'.$search.'%');
                        });
                });
            })
            ->when(filled($filters['action'] ?? null), fn ($query) => $query->where('action', $filters['action']))
            ->when(filled($filters['role'] ?? null), fn ($query) => $query->whereHas('actor', fn ($actors) => $actors->where('role', $filters['role'])))
            ->when(filled($filters['result'] ?? null), fn ($query) => $query->where('metadata->result', $filters['result']))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('identity.users.audit', [
            'pageTitle' => 'Account Audit Log',
            'logs' => $logs,
            'filters' => $filters,
            'actions' => $actions,
            'results' => $results,
            'routeBase' => $this->routeBase($request->user()),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management', 'route' => 'res.users.index'],
                ['label' => 'Audit Log'],
            ],
        ]);
    }

    /** @return array<string, int> */
    private function managementCounts($query): array
    {
        $all = (clone $query)->count();
        $byRole = (clone $query)
            ->select('role')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        return [
            'all' => $all,
            'advisers' => (int) ($byRole[UserRole::Adviser->value] ?? 0),
            'reviewers' => (int) ($byRole[UserRole::Reviewer->value] ?? 0),
            'applicants' => (int) ($byRole[UserRole::Applicant->value] ?? 0),
        ];
    }

    /** @return array<int, array{label: string, value: int, icon: string}> */
    private function accountMetrics(User $user): array
    {
        return match ($user->role) {
            UserRole::Applicant => [
                ['label' => 'Applications', 'value' => $user->researchApplications()->count(), 'icon' => 'file-text'],
            ],
            UserRole::Adviser => [
                ['label' => 'Advised Applications', 'value' => $user->advisedApplications()->count(), 'icon' => 'clipboard'],
            ],
            UserRole::Reviewer => [
                ['label' => 'Review Assignments', 'value' => $user->reviewerAssignments()->count(), 'icon' => 'file-search'],
            ],
            UserRole::ResLead => [],
        };
    }

    private function routeBase(User $actor): string
    {
        return $actor->role === UserRole::ResLead ? 'res.users' : 'adviser.applicants';
    }
}
