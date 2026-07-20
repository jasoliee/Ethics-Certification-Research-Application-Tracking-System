<?php

namespace App\Services\Identity;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

class UsernameGenerator
{
    private const MAX_LENGTH = 30;

    public function generate(
        string $firstName,
        string $lastName,
        UserRole $role,
        ?ApplicantType $applicantType,
    ): string {
        $first = $this->segment($firstName, 'user');
        $last = $this->segment($lastName, 'account');
        $roleSegment = match ($role) {
            UserRole::Applicant => $applicantType === ApplicantType::Faculty ? 'faculty' : 'student',
            UserRole::Adviser => 'adviser',
            UserRole::Reviewer => 'reviewer',
            UserRole::ResLead => 'reslead',
        };
        $base = Str::limit($first.'.'.$last.'_'.$roleSegment, self::MAX_LENGTH, '');
        $candidate = $base;
        $suffix = 2;

        // A deterministic suffix keeps usernames readable while respecting the unique database constraint.
        while (User::query()->where('username', $candidate)->exists()) {
            $ending = (string) $suffix++;
            $candidate = Str::limit($base, self::MAX_LENGTH - strlen($ending), '').$ending;
        }

        return $candidate;
    }

    private function segment(string $value, string $fallback): string
    {
        $segment = Str::of(Str::ascii($value))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->value();

        return $segment !== '' ? $segment : $fallback;
    }
}
