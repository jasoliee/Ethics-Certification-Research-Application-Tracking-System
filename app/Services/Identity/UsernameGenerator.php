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
        $generated = $this->generateBatch([
            ['institutional_identifier' => $institutionalIdentifier, 'last_name' => $lastName],
        ], $reservedUsernames);

        return $generated[0];
    }

    /**
     * Resolve an import batch in a small number of set-based username lookups.
     *
     * @param  array<int, array{institutional_identifier: string, last_name: string}>  $identities
     * @param  array<int, string>  $reservedUsernames
     * @return array<int, string>
     */
    public function generateBatch(array $identities, array $reservedUsernames = []): array
    {
        $states = collect($identities)->map(fn (array $identity): array => [
            'base' => $this->base($identity['institutional_identifier'], $identity['last_name']),
            'suffix' => 1,
            'candidate' => $this->base($identity['institutional_identifier'], $identity['last_name']),
        ])->all();
        $assigned = [];
        $reserved = array_fill_keys($reservedUsernames, true);

        while (count($assigned) < count($states)) {
            $pendingCandidates = collect($states)
                ->reject(fn (array $state, int $index): bool => isset($assigned[$index]))
                ->pluck('candidate')
                ->unique()
                ->values()
                ->all();
            $databaseMatches = User::withTrashed()
                ->whereIn('username', $pendingCandidates)
                ->pluck('username')
                ->flip();

            foreach ($states as $index => &$state) {
                if (isset($assigned[$index])) {
                    continue;
                }

                $candidate = $state['candidate'];

                if (! isset($reserved[$candidate]) && ! $databaseMatches->has($candidate)) {
                    $assigned[$index] = $candidate;
                    $reserved[$candidate] = true;

                    continue;
                }

                $state['suffix']++;
                $ending = (string) $state['suffix'];
                $state['candidate'] = Str::limit($state['base'], self::MAX_LENGTH - strlen($ending), '').$ending;
            }
            unset($state);
        }

        ksort($assigned);

        return array_values($assigned);
    }

    private function base(string $institutionalIdentifier, string $lastName): string
    {
        $identifier = $this->segment($institutionalIdentifier, 'user');
        $last = $this->segment($lastName, 'account');
        $base = Str::limit($identifier.'.'.$last, self::MAX_LENGTH, '');

        return strlen($base) < self::MIN_LENGTH
            ? str_pad($base, self::MIN_LENGTH, '0')
            : $base;
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
