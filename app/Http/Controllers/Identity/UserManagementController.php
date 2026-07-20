<?php

namespace App\Http\Controllers\Identity;

use App\Enums\AccountStatus;
use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangeManagedUserStatusRequest;
use App\Http\Requests\Identity\ImportManagedUsersRequest;
use App\Http\Requests\Identity\StoreManagedUserRequest;
use App\Http\Requests\Identity\UpdateManagedUserRequest;
use App\Models\User;
use App\Services\Identity\AccountCreationAuthorizationService;
use App\Services\Identity\ManagedPasswordResetService;
use App\Services\Identity\UserAccountService;
use App\Services\Identity\UserBulkImportService;
use App\Services\Identity\UserManagementQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserManagementQueryService $queries,
        private readonly AccountCreationAuthorizationService $creationAuthorization,
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
        $institutions = (clone $visible)
            ->whereNotNull('institution')
            ->where('institution', '!=', '')
            ->distinct()
            ->orderBy('institution')
            ->pluck('institution');

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
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management'],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);
        $types = $this->accountTypes($request->user());
        $selectedType = collect($types)->firstWhere('key', (string) $request->query('account_type'));

        return view('identity.users.create', [
            'pageTitle' => 'Add New User',
            'accountTypes' => $types,
            'selectedType' => $selectedType,
            'routeBase' => $this->routeBase($request->user()),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management', 'route' => $this->routeBase($request->user()).'.index'],
                ['label' => 'Add New User'],
            ],
        ]);
    }

    public function store(StoreManagedUserRequest $request, UserAccountService $accounts): RedirectResponse
    {
        $user = $accounts->create($request->user(), $request->validated());

        return redirect()
            ->route($this->routeBase($request->user()).'.show', ['managedUser' => $user, 'created' => 1])
            ->with('status', 'Account created successfully.');
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

    public function changeStatus(
        ChangeManagedUserStatusRequest $request,
        User $managedUser,
        UserAccountService $accounts,
    ): RedirectResponse {
        $accounts->changeStatus($request->user(), $managedUser, $request->validated('account_status'));

        return back()->with('status', 'Account status updated.');
    }

    public function sendPasswordReset(
        Request $request,
        User $managedUser,
        ManagedPasswordResetService $passwordResets,
    ): RedirectResponse {
        $passwordResets->send($request->user(), $managedUser);

        return back()->with('status', 'A secure password reset link was sent to the account email.');
    }

    public function importForm(Request $request): View
    {
        Gate::authorize('import', User::class);

        return view('identity.users.import', [
            'pageTitle' => 'Bulk Account Import',
            'routeBase' => $this->routeBase($request->user()),
            'allowedTypes' => collect($this->accountTypes($request->user()))->pluck('key'),
            'breadcrumbs' => [
                ['label' => 'Home', 'route' => 'dashboard'],
                ['label' => 'User Management', 'route' => $this->routeBase($request->user()).'.index'],
                ['label' => 'Bulk Account Import'],
            ],
        ]);
    }

    public function import(ImportManagedUsersRequest $request, UserBulkImportService $imports): RedirectResponse
    {
        $result = $imports->import($request->user(), $request->file('accounts_file'));

        return redirect()
            ->route($this->routeBase($request->user()).'.index')
            ->with('status', $result['created'].' accounts imported successfully.');
    }

    public function template(Request $request): StreamedResponse
    {
        Gate::authorize('import', User::class);
        $headers = [
            'account_type',
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'email',
            'institutional_identifier',
            'phone_number',
            'institution',
            'department',
            'position_title',
            'password',
        ];

        // The template contains formatting headers only, so no example credentials are distributed.
        return response()->streamDownload(function () use ($headers): void {
            $stream = fopen('php://output', 'wb');

            if ($stream !== false) {
                fputcsv($stream, $headers, ',', '"', '\\');
                fclose($stream);
            }
        }, 'ecrats-account-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
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

    /** @return array<int, array{key: string, label: string, description: string, role: string, applicant_type: string|null, icon: string}> */
    private function accountTypes(User $actor): array
    {
        $allowedRoles = $this->creationAuthorization->allowedRoles($actor);
        $types = [];

        if (in_array(UserRole::Applicant, $allowedRoles, true)) {
            $types[] = ['key' => 'student_researcher', 'label' => 'Student Researcher', 'description' => 'Can prepare and manage student research applications.', 'role' => UserRole::Applicant->value, 'applicant_type' => ApplicantType::Student->value, 'icon' => 'user'];
            $types[] = ['key' => 'faculty_researcher', 'label' => 'Faculty Researcher', 'description' => 'Can prepare and manage faculty research applications.', 'role' => UserRole::Applicant->value, 'applicant_type' => ApplicantType::Faculty->value, 'icon' => 'user-check'];
        }

        if (in_array(UserRole::Adviser, $allowedRoles, true)) {
            $types[] = ['key' => 'adviser', 'label' => 'Research Adviser', 'description' => 'Can review and endorse assigned applicant submissions.', 'role' => UserRole::Adviser->value, 'applicant_type' => null, 'icon' => 'user-check'];
        }

        if (in_array(UserRole::Reviewer, $allowedRoles, true)) {
            $types[] = ['key' => 'reviewer', 'label' => 'Ethics Reviewer', 'description' => 'Can evaluate assigned anonymized ethics applications.', 'role' => UserRole::Reviewer->value, 'applicant_type' => null, 'icon' => 'users'];
        }

        return $types;
    }

    private function routeBase(User $actor): string
    {
        return $actor->role === UserRole::ResLead ? 'res.users' : 'adviser.applicants';
    }
}
