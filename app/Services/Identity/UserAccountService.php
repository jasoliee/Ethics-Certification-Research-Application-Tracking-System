<?php

namespace App\Services\Identity;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public function __construct(
        private readonly AccountCreationAuthorizationService $authorization,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(User $actor, array $attributes): User
    {
        $attributes['username'] = trim((string) ($attributes['username'] ?? ''));
        $attributes['role'] = $attributes['role'] instanceof UserRole
            ? $attributes['role']->value
            : (string) ($attributes['role'] ?? '');
        $attributes['applicant_type'] = $attributes['applicant_type'] instanceof ApplicantType
            ? $attributes['applicant_type']->value
            : ($attributes['applicant_type'] ?? null);

        $targetRole = UserRole::tryFrom($attributes['role']);

        if (! $targetRole || ! $this->authorization->canCreate($actor, $targetRole)) {
            throw new AuthorizationException('You are not allowed to create this account type.');
        }

        $validated = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:30', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:16'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'applicant_type' => [
                Rule::requiredIf($targetRole === UserRole::Applicant),
                'nullable',
                Rule::enum(ApplicantType::class),
            ],
        ], [
            'username.required' => 'Enter a username.',
            'username.max' => 'Username must not exceed 30 characters.',
            'username.unique' => 'Username already exists.',
            'password.required' => 'Enter a password.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.max' => 'Password must not exceed 16 characters.',
        ])->validate();

        return User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $targetRole,
            'applicant_type' => $targetRole === UserRole::Applicant
                ? $validated['applicant_type']
                : null,
            'account_status' => 'active',
        ]);
    }
}
