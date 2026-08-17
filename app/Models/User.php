<?php

namespace App\Models;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
    'program',
    'year_level',
    'position_title',
    'expected_endorsement_count',
    'certificate_signatory_name',
    'certificate_signature_path',
    'certificate_signature_sha256',
    'certificate_signature_width',
    'certificate_signature_height',
    'certificate_signature_uploaded_at',
    'reviewer_classification',
    'reviewer_classifications',
    'reviewer_capacity',
    'reviewer_enabled',
    'password',
    'role',
    'applicant_type',
    'account_status',
    'created_by_user_id',
    'password_changed_at',
    'password_setup_completed_at',
    'onboarding_completed_at',
    'setup_email_status',
    'setup_email_sent_at',
    'setup_email_failed_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
            'expected_endorsement_count' => 'integer',
            'certificate_signature_width' => 'integer',
            'certificate_signature_height' => 'integer',
            'certificate_signature_uploaded_at' => 'datetime',
            'reviewer_capacity' => 'integer',
            'reviewer_enabled' => 'boolean',
            'reviewer_classifications' => 'array',
            'password_changed_at' => 'datetime',
            'password_setup_completed_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'setup_email_sent_at' => 'datetime',
            'setup_email_failed_at' => 'datetime',
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

    /**
     * Determine whether this account may enter supplementary Reviewer features.
     *
     * Route middleware re-queries this state on every request so an RES Lead's
     * disable action takes effect immediately even for an existing session.
     */
    public function hasReviewerAccess(): bool
    {
        return $this->role === UserRole::Adviser
            && $this->account_status === 'active'
            && $this->reviewer_enabled === true
            && ! $this->trashed();
    }

    /** @return array<int, string> */
    public function reviewerClassificationLabels(): array
    {
        return collect($this->reviewer_classifications ?? [])
            ->filter(fn (mixed $classification): bool => is_string($classification) && trim($classification) !== '')
            ->map(fn (string $classification): string => trim($classification))
            ->unique(fn (string $classification): string => mb_strtolower($classification))
            ->values()
            ->all();
    }

    public function hasReviewerClassification(string $classification): bool
    {
        $expected = $this->normalizeReviewerClassification($classification);

        return collect($this->reviewerClassificationLabels())
            ->contains(fn (string $value): bool => $this->normalizeReviewerClassification($value) === $expected);
    }

    public function scopeReviewerEnabled(Builder $query): Builder
    {
        return $query
            ->where('role', UserRole::Adviser->value)
            ->where('account_status', 'active')
            ->where('reviewer_enabled', true);
    }

    private function normalizeReviewerClassification(string $classification): string
    {
        return mb_strtolower(str_replace(['_', '-'], ' ', trim($classification)));
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

    public function endorsements(): HasMany
    {
        return $this->hasMany(Endorsement::class, 'adviser_user_id');
    }

    public function reviewerAssignments(): HasMany
    {
        return $this->hasMany(ReviewerAssignment::class, 'reviewer_user_id');
    }

    public function reviewerIdentityReconciliationsAsSource(): HasMany
    {
        return $this->hasMany(ReviewerIdentityReconciliation::class, 'source_user_id');
    }

    public function reviewerIdentityReconciliationsAsTarget(): HasMany
    {
        return $this->hasMany(ReviewerIdentityReconciliation::class, 'target_adviser_user_id');
    }

    public function reviewerConflicts(): HasMany
    {
        return $this->hasMany(ReviewerConflict::class, 'reviewer_user_id');
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
