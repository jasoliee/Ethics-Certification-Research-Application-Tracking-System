<?php

namespace App\Http\Controllers\Identity;

use App\Enums\AccountStatus;
use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangeManagedUserStatusRequest;
use App\Http\Requests\Identity\ChangeProfileOptionStatusRequest;
use App\Http\Requests\Identity\ConfirmManagedUserImportRequest;
use App\Http\Requests\Identity\ImportManagedUsersRequest;
use App\Http\Requests\Identity\MassManagedUserActionRequest;
use App\Http\Requests\Identity\RegenerateManagedUsernameRequest;
use App\Http\Requests\Identity\RestoreArchivedImportRequest;
use App\Http\Requests\Identity\StoreManagedUserRequest;
use App\Http\Requests\Identity\StoreProfileOptionRequest;
use App\Http\Requests\Identity\UpdateManagedUserRequest;
use App\Http\Requests\Identity\UpdateProfileOptionRequest;
use App\Models\AuditLog;
use App\Models\ProfileOption;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

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
            'canDelete' => $request->user()->can('delete', $managedUser),
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

        return redirect()
            ->route('res.users.profile-options.index', ['field' => $field->value])
            ->with('status', "{$field->label()} option added.");
    }

    public function profileOptionsIndex(Request $request): View
    {
        Gate::authorize('manageProfileOptions', User::class);
        $filters = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'field' => ['nullable', Rule::enum(ProfileOptionField::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ])->validate();
        $search = trim((string) ($filters['search'] ?? ''));
        $query = ProfileOption::query()
            ->select(['id', 'field', 'value', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->when($search !== '', fn ($options) => $options->whereLike('value', '%'.$search.'%'))
            ->when(filled($filters['field'] ?? null), fn ($options) => $options->where('field', $filters['field']))
            ->when(($filters['status'] ?? null) === 'active', fn ($options) => $options->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($options) => $options->where('is_active', false))
            ->orderBy('field')
            ->orderBy('sort_order')
            ->orderBy('value');
        $options = $query->paginate(20)->withQueryString();

        return view('identity.users.options', [
            'pageTitle' => 'Dropdown Option Management',
            'options' => $options,
            'usageCounts' => $this->profileOptions->usageCounts(collect($options->items())),
            'filters' => $filters,
            'counts' => [
                'active' => ProfileOption::query()->where('is_active', true)->count(),
                'inactive' => ProfileOption::query()->where('is_active', false)->count(),
            ],
            'routeBase' => 'res.users',
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management', 'route' => 'res.users.index'],
                ['label' => 'Dropdown Options'],
            ],
        ]);
    }

    public function updateProfileOption(
        UpdateProfileOptionRequest $request,
        ProfileOption $profileOption,
        ProfileOptionCatalog $profileOptions,
    ): RedirectResponse {
        $profileOptions->update($request->user(), $profileOption, $request->validated('option_value'));

        return back()->with('status', 'Dropdown option updated. Existing account values were left unchanged.');
    }

    public function changeProfileOptionStatus(
        ChangeProfileOptionStatusRequest $request,
        ProfileOption $profileOption,
        ProfileOptionCatalog $profileOptions,
    ): RedirectResponse {
        $isActive = $request->boolean('is_active');
        $profileOptions->setActive($request->user(), $profileOption, $isActive);

        return back()->with('status', $isActive
            ? 'Dropdown option restored for new selections.'
            : 'Dropdown option deactivated. Historical account values were preserved.');
    }

    public function changeStatus(
        ChangeManagedUserStatusRequest $request,
        User $managedUser,
        UserAccountService $accounts,
    ): RedirectResponse {
        $accounts->changeStatus($request->user(), $managedUser, $request->validated('account_status'));

        return back()->with('status', 'Account status updated.');
    }

    /**
     * Archive one authorized account without deleting its related history.
     */
    public function destroy(
        Request $request,
        User $managedUser,
        UserAccountService $accounts,
    ): RedirectResponse {
        $accounts->archive($request->user(), $managedUser);

        return redirect()
            ->route('res.users.index')
            ->with('status', 'Account moved to archived records.');
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

    public function importForm(
        Request $request,
        UserBulkImportService $imports,
    ): View|RedirectResponse {
        Gate::authorize('import', User::class);
        $accountType = (string) $request->query('account_type');

        if ($accountType === '') {
            return redirect()->route($this->routeBase($request->user()).'.create');
        }

        $selectedType = $this->accountTypes->authorized($request->user(), $accountType);
        $previewToken = $request->session()->get('import_preview_token');

        // Restore redirects keep the authoritative preview token server-side in one flash session value.
        $preview = is_string($previewToken)
            ? $imports->previewFor($request->user(), $previewToken)
            : null;

        return view('identity.users.import', [
            'pageTitle' => 'Bulk Account Import',
            'routeBase' => $this->routeBase($request->user()),
            'selectedType' => $selectedType,
            'preview' => $preview,
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
            ->with('status', "{$result['created']} accounts created; {$result['existing']} newly existing accounts skipped; {$result['failed']} rows failed. {$result['emails_sent']} setup emails sent; {$result['emails_failed']} failed or remain pending.");
    }

    /**
     * Restore one archived account after confirming its ID belongs to the private preview.
     */
    public function restoreImportAccount(
        RestoreArchivedImportRequest $request,
        UserBulkImportService $imports,
    ): RedirectResponse {
        $archivedUserId = $request->validated('archived_user_id');

        // HTML form integers arrive as strings, so require a value before casting the validated ID.
        if ($archivedUserId === null) {
            throw ValidationException::withMessages([
                'archived_user_id' => 'Select one archived account to restore.',
            ]);
        }

        $result = $imports->restore(
            $request->user(),
            $request->validated('import_token'),
            (int) $archivedUserId,
        );

        return $this->restorationRedirect($request, $result);
    }

    /**
     * Restore only the archived accounts listed by the current private preview.
     */
    public function restoreImportAccounts(
        RestoreArchivedImportRequest $request,
        UserBulkImportService $imports,
    ): RedirectResponse {
        $result = $imports->restore(
            $request->user(),
            $request->validated('import_token'),
        );

        return $this->restorationRedirect($request, $result);
    }

    /**
     * Generate a verified role-specific workbook before attaching any spreadsheet response headers.
     */
    public function template(Request $request, SafeSpreadsheet $spreadsheets): BinaryFileResponse|RedirectResponse
    {
        // Preserve the existing policy and role-catalog checks before any private workbook work begins.
        Gate::authorize('import', User::class);
        $type = $this->accountTypes->authorized($request->user(), (string) $request->query('account_type'));
        $filename = 'ecrats-'.$type['key'].'-template.xlsx';
        $optionValues = $this->profileOptions->grouped();

        try {
            // Complete generation and verification first so a failure returns an ordinary application redirect, never a mislabeled XLSX.
            $path = $spreadsheets->createTemplate($type, $optionValues);
        } catch (Throwable $exception) {
            // Record only bounded operational context; exception text can contain internal paths or library diagnostics.
            Log::warning('Account template generation failed.', [
                'actor_user_id' => $request->user()->id,
                'account_type' => $type['key'],
                'exception_class' => $exception::class,
            ]);

            // Return a neutral message through the normal import page without spreadsheet attachment headers.
            return back()->withErrors([
                'template' => 'The Excel template could not be generated. Please try again.',
            ]);
        }

        // Attach only the verified private file and ask Symfony to remove it after the response is delivered.
        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, private',
            ])
            ->deleteFileAfterSend(true);
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
            'target_type' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ])->validate();
        $hiddenActions = ['user.onboarding_completed', 'user.password_setup_completed'];
        $search = trim((string) ($filters['search'] ?? ''));
        $baseQuery = AuditLog::query()->whereNotIn('action', $hiddenActions);
        $actions = (clone $baseQuery)->distinct()->orderBy('action')->pluck('action');
        $targetTypes = (clone $baseQuery)->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type');
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
            ->when(filled($filters['target_type'] ?? null), fn ($query) => $query->where('subject_type', $filters['target_type']))
            ->when(filled($filters['date_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('identity.users.audit', [
            'pageTitle' => 'Account Audit Log',
            'logs' => $logs,
            'filters' => $filters,
            'actions' => $actions,
            'targetTypes' => $targetTypes,
            'routeBase' => $this->routeBase($request->user()),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management', 'route' => 'res.users.index'],
                ['label' => 'Audit Log'],
            ],
        ]);
    }

    /**
     * Redirect restoration results through the import form while keeping the preview token out of the URL.
     *
     * @param  array{preview: array<string, mixed>, restored_now: int, conflicts_now: int}  $result
     */
    private function restorationRedirect(Request $request, array $result): RedirectResponse
    {
        $preview = $result['preview'];
        $restored = $result['restored_now'];
        $conflicts = $result['conflicts_now'];
        $message = "{$restored} archived ".str('account')->plural($restored).' restored.';

        // Include conflict totals without exposing internal database or exception details.
        if ($conflicts > 0) {
            $message .= " {$conflicts} ".str('account')->plural($conflicts).' remained archived because of conflicts.';
        }

        return redirect()
            ->route($this->routeBase($request->user()).'.import.form', [
                'account_type' => $preview['account_type']['key'],
            ])
            ->with('status', $message)
            ->with('import_preview_token', $preview['preview_token']);
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
