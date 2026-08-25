<?php

namespace Database\Factories;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Keep legacy test fixtures compatible without ever persisting a standalone
     * Reviewer role. An explicit Reviewer override now represents an entitled Adviser.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if ($user->role !== UserRole::Reviewer) {
                return;
            }

            $classification = filled($user->reviewer_classification)
                ? trim((string) $user->reviewer_classification)
                : 'Expedited';

            $user->forceFill([
                'role' => UserRole::Adviser,
                'applicant_type' => null,
                'reviewer_enabled' => true,
                'reviewer_classification' => $classification,
                'reviewer_classifications' => $user->reviewer_classifications ?: [$classification],
                'reviewer_capacity' => $user->reviewer_capacity ?: 6,
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'name' => User::formatName($firstName, null, $lastName, null),
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'suffix' => null,
            'username' => fake()->unique()->bothify('user????####'),
            'email' => fake()->unique()->safeEmail(),
            'institutional_identifier' => fake()->unique()->bothify('KLD-####??'),
            'phone_number' => null,
            'institution' => 'Kolehiyo ng Lungsod ng Dasmarinas',
            'program' => null,
            'year_level' => null,
            'position_title' => null,
            'reviewer_classification' => null,
            'reviewer_classifications' => null,
            'reviewer_capacity' => null,
            'reviewer_enabled' => false,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
            'account_status' => 'active',
            'created_by_user_id' => null,
            'password_changed_at' => now(),
            'password_setup_completed_at' => now(),
            'onboarding_completed_at' => now(),
            'setup_email_status' => 'not_required',
            'remember_token' => Str::random(10),
        ];
    }

    public function pendingSetup(): static
    {
        return $this->state(fn (): array => [
            'account_status' => 'pending_setup',
            'password_changed_at' => null,
            'password_setup_completed_at' => null,
            'onboarding_completed_at' => null,
            'setup_email_status' => 'not_sent',
        ]);
    }

    /** Create an Adviser account with supplementary Reviewer capability. */
    public function reviewer(array $classifications = ['Expedited']): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Adviser,
            'applicant_type' => null,
            'position_title' => 'Ethics Reviewer',
            'reviewer_classification' => $classifications[0] ?? null,
            'reviewer_classifications' => array_values($classifications),
            'reviewer_capacity' => 6,
            'reviewer_enabled' => true,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
