<?php

namespace App\Services\Identity;

use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\ProfileOption;
use App\Models\ProfileOptionAlias;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileOptionCatalog
{
    /** @var array<string, array<int, string>>|null */
    private ?array $loadedOptions = null;

    /** @var array<string, array<string, ProfileOption>>|null */
    private ?array $loadedIdentities = null;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /** @return array<string, array<int, string>> */
    public function grouped(): array
    {
        if ($this->loadedOptions === null) {
            $this->loadedOptions = collect(ProfileOptionField::cases())
                ->mapWithKeys(fn (ProfileOptionField $field): array => [$field->value => []])
                ->all();
            $options = ProfileOption::query()
                ->where('is_active', true)
                ->orderBy('field')
                ->orderBy('sort_order')
                ->orderBy('value')
                ->get(['field', 'value']);

            foreach ($options as $option) {
                $this->loadedOptions[$option->field->value][] = $option->value;
            }
        }

        return $this->loadedOptions;
    }

    /** @return array<int, string> */
    public function values(ProfileOptionField $field, ?string $currentValue = null): array
    {
        $values = $this->grouped()[$field->value] ?? [];

        if (filled($currentValue)
            && ! collect($values)->contains(fn (string $value): bool => Str::lower($value) === Str::lower((string) $currentValue))) {
            $values[] = (string) $currentValue;
        }

        return $values;
    }

    /** @return array<string, array<int, string>> */
    public function groupedForUser(User $user): array
    {
        return [
            ProfileOptionField::YearLevel->value => $this->values(ProfileOptionField::YearLevel, $user->year_level),
            ProfileOptionField::Institution->value => $this->values(ProfileOptionField::Institution, $user->institution),
            ProfileOptionField::Department->value => $this->values(ProfileOptionField::Department, $user->department),
            ProfileOptionField::Program->value => $this->values(ProfileOptionField::Program, $user->program),
            ProfileOptionField::ReviewerClassification->value => $this->values(
                ProfileOptionField::ReviewerClassification,
                $user->reviewer_classification,
            ),
        ];
    }

    public function validationMessage(ProfileOptionField $field): string
    {
        $values = $this->values($field);

        if ($values === []) {
            return "No accepted {$field->label()} options are configured. Ask the RES Lead to add one first.";
        }

        return "Select an accepted {$field->label()}: ".implode(', ', $values).'.';
    }

    /**
     * Resolve an active current label, historical alias, or immutable numeric ID.
     */
    public function resolve(ProfileOptionField $field, string|int|null $identity): ?ProfileOption
    {
        if (! filled($identity)) {
            return null;
        }

        $identities = $this->identityMap()[$field->value] ?? [];
        $value = Str::squish((string) $identity);

        if (ctype_digit($value) && isset($identities['id:'.$value])) {
            return $identities['id:'.$value];
        }

        return $identities['label:'.Str::lower($value)] ?? null;
    }

    public function create(User $actor, ProfileOptionField|string $field, string $value): ProfileOption
    {
        $this->authorize($actor);
        $field = $field instanceof ProfileOptionField ? $field : ProfileOptionField::from($field);
        [$value, $normalized] = $this->normalizedValue($value);

        if (ProfileOption::query()->where('field', $field->value)->where('normalized_value', $normalized)->exists()
            || ProfileOptionAlias::query()->where('field', $field->value)->where('normalized_value', $normalized)->exists()) {
            throw ValidationException::withMessages([
                'option_value' => "{$value} already belongs to an existing {$field->label()} identity. Restore or rename that option instead.",
            ]);
        }

        $option = ProfileOption::create([
            'field' => $field,
            'value' => $value,
            'normalized_value' => $normalized,
            'sort_order' => ((int) ProfileOption::query()->where('field', $field->value)->max('sort_order')) + 10,
            'is_active' => true,
            'created_by_user_id' => $actor->id,
        ]);
        $this->resetCache();

        $this->auditLog->record($actor, 'user.profile_option_created', $option, [
            'option_id' => $option->id,
            'field' => $field->value,
            'value' => $value,
            'result' => 'created',
        ]);

        return $option;
    }

    public function update(User $actor, ProfileOption $option, string $value): ProfileOption
    {
        $this->authorize($actor);
        [$value, $normalized] = $this->normalizedValue($value);

        $updated = DB::transaction(function () use ($actor, $option, $value, $normalized): ProfileOption {
            // Lock the identity while moving its previous readable label into alias history.
            $locked = ProfileOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();
            $field = $locked->field;
            $conflictingCurrent = ProfileOption::query()
                ->where('field', $field->value)
                ->where('normalized_value', $normalized)
                ->whereKeyNot($locked->id)
                ->exists();
            $conflictingAlias = ProfileOptionAlias::query()
                ->where('field', $field->value)
                ->where('normalized_value', $normalized)
                ->where('profile_option_id', '!=', $locked->id)
                ->exists();

            if ($conflictingCurrent || $conflictingAlias) {
                throw ValidationException::withMessages([
                    'option_value' => "{$value} already belongs to another {$field->label()} identity.",
                ]);
            }

            $previousValue = $locked->value;
            $previousNormalized = $locked->normalized_value;

            if ($previousNormalized !== $normalized) {
                // Restoring an earlier label removes that duplicate alias before preserving the outgoing label.
                ProfileOptionAlias::query()
                    ->where('profile_option_id', $locked->id)
                    ->where('normalized_value', $normalized)
                    ->delete();
                ProfileOptionAlias::query()->firstOrCreate(
                    [
                        'field' => $field->value,
                        'normalized_value' => $previousNormalized,
                    ],
                    [
                        'profile_option_id' => $locked->id,
                        'value' => $previousValue,
                    ],
                );
            }

            $locked->update(['value' => $value, 'normalized_value' => $normalized]);

            $this->auditLog->record($actor, 'user.profile_option_updated', $locked, [
                'option_id' => $locked->id,
                'field' => $field->value,
                'previous_value' => $previousValue,
                'value' => $value,
                'result' => 'updated',
            ]);

            return $locked->refresh();
        }, 3);
        $this->resetCache();

        return $updated;
    }

    public function setActive(User $actor, ProfileOption $option, bool $isActive): ProfileOption
    {
        $this->authorize($actor);

        if ($option->is_active === $isActive) {
            return $option;
        }

        $option->update(['is_active' => $isActive]);
        $this->resetCache();

        $this->auditLog->record(
            $actor,
            $isActive ? 'user.profile_option_restored' : 'user.profile_option_deactivated',
            $option,
            [
                'option_id' => $option->id,
                'field' => $option->field->value,
                'value' => $option->value,
                'result' => $isActive ? 'restored' : 'deactivated',
            ],
        );

        return $option->refresh();
    }

    /**
     * Resolve option usage in at most one grouped query per field instead of querying once per table row.
     *
     * @param  Collection<int, ProfileOption>  $options
     * @return array<int, int>
     */
    public function usageCounts(Collection $options): array
    {
        $columns = [
            ProfileOptionField::YearLevel->value => 'year_level',
            ProfileOptionField::Institution->value => 'institution',
            ProfileOptionField::Department->value => 'department',
            ProfileOptionField::Program->value => 'program',
            ProfileOptionField::ReviewerClassification->value => 'reviewer_classification',
        ];
        $options->loadMissing('aliases:id,profile_option_id,value');
        $usage = $options->mapWithKeys(fn (ProfileOption $option): array => [$option->id => 0])->all();

        foreach ($options->groupBy(fn (ProfileOption $option): string => $option->field->value) as $field => $fieldOptions) {
            $column = $columns[$field] ?? null;

            if ($column === null) {
                continue;
            }

            $acceptedValues = $fieldOptions
                ->flatMap(fn (ProfileOption $option): array => [
                    $option->value,
                    ...$option->aliases->pluck('value')->all(),
                ])
                ->unique()
                ->values();
            $counts = User::withTrashed()
                ->select($column)
                ->selectRaw('COUNT(*) AS aggregate')
                ->whereIn($column, $acceptedValues)
                ->groupBy($column)
                ->pluck('aggregate', $column);

            foreach ($fieldOptions as $option) {
                $usage[$option->id] = collect([
                    $option->value,
                    ...$option->aliases->pluck('value')->all(),
                ])->sum(fn (string $value): int => (int) ($counts[$value] ?? 0));
            }
        }

        return $usage;
    }

    private function authorize(User $actor): void
    {
        if ($actor->role !== UserRole::ResLead) {
            throw new AuthorizationException('Only the RES Lead may manage shared dropdown options.');
        }
    }

    /**
     * Load active identity and alias mappings once so a 250-row import does not query per cell.
     *
     * @return array<string, array<string, ProfileOption>>
     */
    private function identityMap(): array
    {
        if ($this->loadedIdentities !== null) {
            return $this->loadedIdentities;
        }

        $this->loadedIdentities = collect(ProfileOptionField::cases())
            ->mapWithKeys(fn (ProfileOptionField $field): array => [$field->value => []])
            ->all();
        $options = ProfileOption::query()
            ->with('aliases:id,profile_option_id,normalized_value')
            ->where('is_active', true)
            ->get();

        foreach ($options as $option) {
            $field = $option->field->value;
            $this->loadedIdentities[$field]['id:'.$option->id] = $option;
            $this->loadedIdentities[$field]['label:'.$option->normalized_value] = $option;

            foreach ($option->aliases as $alias) {
                $this->loadedIdentities[$field]['label:'.$alias->normalized_value] = $option;
            }
        }

        return $this->loadedIdentities;
    }

    private function resetCache(): void
    {
        $this->loadedOptions = null;
        $this->loadedIdentities = null;
    }

    /** @return array{0: string, 1: string} */
    private function normalizedValue(string $value): array
    {
        $value = Str::squish($value);

        return [$value, Str::lower($value)];
    }
}
