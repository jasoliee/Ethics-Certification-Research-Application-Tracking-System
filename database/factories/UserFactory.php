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
            'department' => null,
            'program' => null,
            'year_level' => null,
            'position_title' => null,
            'reviewer_classification' => null,
            'reviewer_capacity' => null,
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
