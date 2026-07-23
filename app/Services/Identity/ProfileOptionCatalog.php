<?php

namespace App\Services\Identity;

use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\ProfileOption;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
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
        if ($actor->role !== UserRole::ResLead) {
            throw new AuthorizationException('Only the RES Lead may manage shared dropdown options.');
        }

        $field = $field instanceof ProfileOptionField ? $field : ProfileOptionField::from($field);
        $value = Str::squish($value);
        $normalized = Str::lower($value);

        if (ProfileOption::query()->where('field', $field->value)->where('normalized_value', $normalized)->exists()) {
            throw ValidationException::withMessages([
                'option_value' => "{$value} is already available under {$field->label()}.",
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
}
