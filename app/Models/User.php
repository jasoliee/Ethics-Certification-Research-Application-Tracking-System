<?php

namespace App\Models;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'applicant_type', 'account_status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'applicant_type' => ApplicantType::class,
        ];
    }

    public function displayRoleLabel(): string
    {
        if ($this->role === UserRole::Applicant) {
            return ($this->applicant_type ?? ApplicantType::Student)->label();
        }

        return $this->role->label();
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : trim($value),
        );
    }

    public function researchApplications(): HasMany
    {
        return $this->hasMany(ResearchApplication::class, 'applicant_user_id');
    }

    public function advisedApplications(): HasMany
    {
        return $this->hasMany(ResearchApplication::class, 'adviser_user_id');
    }

    public function reviewerAssignments(): HasMany
    {
        return $this->hasMany(ReviewerAssignment::class, 'reviewer_user_id');
    }
}
