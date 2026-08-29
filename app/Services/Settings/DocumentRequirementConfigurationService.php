<?php

namespace App\Services\Settings;

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manages the shared application-document catalogue without deleting historical links.
 */
class DocumentRequirementConfigurationService
{
    public function __construct(
        private readonly AcademicTermResolver $terms,
        private readonly AuditLogService $auditLog,
    ) {}

    public function structuralChangesLocked(): ?AcademicTerm
    {
        return $this->terms->currentOrPaused();
    }

    /** @param array{name: string, description?: string|null} $attributes */
    public function create(User $actor, array $attributes): DocumentRequirement
    {
        $this->authorize($actor);
        $this->assertStructuralChangesAllowed();

        return DB::transaction(function () use ($actor, $attributes): DocumentRequirement {
            $catalog = DocumentRequirement::query()
                ->lockForUpdate()
                ->get(['id', 'code', 'sort_order']);
            $name = Str::squish($attributes['name']);
            $requirement = DocumentRequirement::create([
                'code' => $this->uniqueCode($name, $catalog->pluck('code')->all()),
                'name' => $name,
                'description' => $this->description($attributes['description'] ?? null),
                'is_mandatory' => true,
                'research_types' => null,
                'sort_order' => max(0, (int) $catalog->max('sort_order')) + 10,
                'is_active' => true,
            ]);

            $this->auditLog->record($actor, 'settings.document_requirement_created', $requirement, [
                'code' => $requirement->code,
                'name' => $requirement->name,
                'result' => 'active_requirement_created',
            ]);

            return $requirement;
        }, 3);
    }

    /** @param array{name: string, description?: string|null} $attributes */
    public function update(User $actor, DocumentRequirement $requirement, array $attributes): DocumentRequirement
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $requirement, $attributes): DocumentRequirement {
            $locked = DocumentRequirement::query()->whereKey($requirement->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->is_active, 404);

            $before = $locked->only(['name', 'description']);
            $locked->update([
                'name' => Str::squish($attributes['name']),
                'description' => $this->description($attributes['description'] ?? null),
            ]);

            $this->auditLog->record($actor, 'settings.document_requirement_updated', $locked, [
                'code' => $locked->code,
                'before' => $before,
                'after' => $locked->only(['name', 'description']),
                'result' => 'requirement_text_updated',
            ]);

            return $locked;
        }, 3);
    }

    public function deactivate(User $actor, DocumentRequirement $requirement): void
    {
        $this->authorize($actor);
        $this->assertStructuralChangesAllowed();

        DB::transaction(function () use ($actor, $requirement): void {
            $locked = DocumentRequirement::query()->whereKey($requirement->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                return;
            }

            $documentCount = $locked->applicationDocuments()->count();
            $locked->update(['is_active' => false]);
            $this->auditLog->record($actor, 'settings.document_requirement_deactivated', $locked, [
                'code' => $locked->code,
                'name' => $locked->name,
                'preserved_application_document_count' => $documentCount,
                'result' => 'removed_from_active_catalogue',
            ]);
        }, 3);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->role === UserRole::ResLead, 403);
    }

    private function assertStructuralChangesAllowed(): void
    {
        $term = $this->structuralChangesLocked();

        if ($term !== null) {
            throw ValidationException::withMessages([
                'requirements' => "Requirements cannot be added or deleted while {$term->label()} is active. Existing requirement text may still be edited.",
            ])->errorBag('requirementConfiguration');
        }
    }

    private function description(?string $description): ?string
    {
        $description = trim((string) $description);

        return $description === '' ? null : $description;
    }

    /** @param array<int, string> $existingCodes */
    private function uniqueCode(string $name, array $existingCodes): string
    {
        $slug = Str::upper(Str::slug($name));
        $base = Str::limit('REQ-'.($slug !== '' ? $slug : 'DOCUMENT'), 52, '');
        $existing = array_fill_keys(array_map(
            static fn (string $code): string => Str::upper($code),
            $existingCodes,
        ), true);
        $candidate = $base;
        $suffix = 2;

        while (isset($existing[$candidate])) {
            $ending = '-'.$suffix++;
            $candidate = Str::limit($base, 60 - strlen($ending), '').$ending;
        }

        return $candidate;
    }
}
