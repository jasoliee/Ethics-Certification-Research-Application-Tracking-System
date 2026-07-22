<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Str;

class UsernameGenerator
{
    private const MAX_LENGTH = 30;

    private const MIN_LENGTH = 6;

    public function generate(
        string $institutionalIdentifier,
        string $lastName,
        array $reservedUsernames = [],
    ): string {
        $identifier = $this->segment($institutionalIdentifier, 'user');
        $last = $this->segment($lastName, 'account');
        $base = Str::limit($identifier.'.'.$last, self::MAX_LENGTH, '');

        if (strlen($base) < self::MIN_LENGTH) {
            $base = str_pad($base, self::MIN_LENGTH, '0');
        }

        $candidate = $base;
        $suffix = 2;

        // A deterministic suffix keeps usernames readable while respecting the unique database constraint.
        while (in_array($candidate, $reservedUsernames, true) || User::query()->where('username', $candidate)->exists()) {
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
