<?php

namespace App\Services\Identity;

use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\ProfileOption;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileOptionCatalog
{
    /** @var array<string, array<int, string>>|null */
    private ?array $loadedOptions = null;

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

    public function create(User $actor, ProfileOptionField|string $field, string $value): ProfileOption
    {
        $this->authorize($actor);
        $field = $field instanceof ProfileOptionField ? $field : ProfileOptionField::from($field);
        [$value, $normalized] = $this->normalizedValue($value);

        if (ProfileOption::query()->where('field', $field->value)->where('normalized_value', $normalized)->exists()) {
            throw ValidationException::withMessages([
                'option_value' => "{$value} already exists under {$field->label()}. Restore the existing option if it is inactive.",
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
        $this->loadedOptions = null;

        $this->auditLog->record($actor, 'user.profile_option_created', $option, [
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

        if (ProfileOption::query()
            ->where('field', $option->field->value)
            ->where('normalized_value', $normalized)
            ->whereKeyNot($option->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'option_value' => "{$value} already exists under {$option->field->label()}.",
            ]);
        }

        $previousValue = $option->value;
        $option->update(['value' => $value, 'normalized_value' => $normalized]);
        $this->loadedOptions = null;

        $this->auditLog->record($actor, 'user.profile_option_updated', $option, [
            'field' => $option->field->value,
            'previous_value' => $previousValue,
            'value' => $value,
            'result' => 'updated',
        ]);

        return $option->refresh();
    }

    public function setActive(User $actor, ProfileOption $option, bool $isActive): ProfileOption
    {
        $this->authorize($actor);

        if ($option->is_active === $isActive) {
            return $option;
        }

        $option->update(['is_active' => $isActive]);
        $this->loadedOptions = null;

        $this->auditLog->record(
            $actor,
            $isActive ? 'user.profile_option_restored' : 'user.profile_option_deactivated',
            $option,
            [
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
        $usage = $options->mapWithKeys(fn (ProfileOption $option): array => [$option->id => 0])->all();

        foreach ($options->groupBy(fn (ProfileOption $option): string => $option->field->value) as $field => $fieldOptions) {
            $column = $columns[$field] ?? null;

            if ($column === null) {
                continue;
            }

            $counts = User::withTrashed()
                ->select($column)
                ->selectRaw('COUNT(*) AS aggregate')
                ->whereIn($column, $fieldOptions->pluck('value')->all())
                ->groupBy($column)
                ->pluck('aggregate', $column);

            foreach ($fieldOptions as $option) {
                $usage[$option->id] = (int) ($counts[$option->value] ?? 0);
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

    /** @return array{0: string, 1: string} */
    private function normalizedValue(string $value): array
    {
        $value = Str::squish($value);

        return [$value, Str::lower($value)];
    }
}
