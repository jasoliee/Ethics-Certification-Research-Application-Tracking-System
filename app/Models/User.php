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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'first_name',
    'middle_name',
    'last_name',
    'suffix',
    'username',
    'email',
    'institutional_identifier',
    'phone_number',
    'institution',
    'department',
    'position_title',
    'password',
    'role',
    'applicant_type',
    'account_status',
    'created_by_user_id',
    'password_changed_at',
])]
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
            'password_changed_at' => 'datetime',
        ];
    }

    /** Build the compatibility display name from the normalized account fields. */
    public static function formatName(
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $suffix,
    ): string {
        return collect([$firstName, $middleName, $lastName, $suffix])
            ->filter(fn (?string $part): bool => filled($part))
            ->map(fn (string $part): string => trim($part))
            ->implode(' ');
    }

    public function displayRoleLabel(): string
    {
        if ($this->role === UserRole::Applicant) {
            return ($this->applicant_type ?? ApplicantType::Student)->label();
        }

        return $this->role->label();
    }

    public function institutionalIdentifierLabel(): string
    {
        return $this->role === UserRole::Applicant && $this->applicant_type === ApplicantType::Student
            ? 'Student Number'
            : 'Employee ID';
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by_user_id');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(self::class, 'created_by_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }
}
