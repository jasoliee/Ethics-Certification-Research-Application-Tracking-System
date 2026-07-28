<?php

namespace App\Services\Settings;

use App\Enums\DeadlineManualStatus;
use App\Models\DeadlineConfiguration;
use App\Models\TimelineCalendarEvent;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\DeadlineProcessCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reads and atomically persists the current RES-managed process schedule.
 */
class DeadlineConfigurationService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * Return one display record per approved process, preferring canonical settings rows.
     *
     * @return array<string, array<string, mixed>>
     */
    public function settings(): array
    {
        $definitions = DeadlineProcessCatalog::definitions();
        $rows = DeadlineConfiguration::query()
            ->where('is_active', true)
            ->where(function ($query) use ($definitions): void {
                foreach (array_keys($definitions) as $index => $key) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('deadline_key', 'like', "%{$key}");
                }
            })
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->get();

        return collect($definitions)->mapWithKeys(function (array $definition, string $key) use ($rows): array {
            $configuration = $rows->firstWhere('deadline_key', $key)
                ?? $rows->first(fn (DeadlineConfiguration $row): bool => str_ends_with($row->deadline_key, $key));

            return [$key => [
                ...$definition,
                'key' => $key,
                'configuration' => $configuration,
                'is_open' => $configuration
                    ? $this->isOpenForDisplay($configuration)
                    : false,
            ]];
        })->all();
    }

    /**
     * Update canonical deadline rows and their matching timeline events in one transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, array $attributes): void
    {
        DB::transaction(function () use ($actor, $attributes): void {
            $semester = trim((string) $attributes['semester_label']);
            $manualStates = [];

            foreach (DeadlineProcessCatalog::definitions() as $key => $definition) {
                $process = $attributes['processes'][$key];
                $startsAt = Carbon::createFromFormat('Y-m-d\TH:i', $process['starts_at']);
                $dueAt = Carbon::createFromFormat('Y-m-d\TH:i', $process['due_at']);
                $manualStatus = $process['is_open']
                    ? DeadlineManualStatus::Open
                    : DeadlineManualStatus::Closed;

                DeadlineConfiguration::query()->updateOrCreate(
                    ['deadline_key' => $key],
                    [
                        'title' => $definition['title'].' Deadline',
                        'audience_role' => $definition['audience_role'],
                        'semester_label' => $semester,
                        'starts_at' => $startsAt,
                        'due_at' => $dueAt,
                        'manual_status' => $manualStatus,
                        'priority' => 100,
                        'is_active' => true,
                    ],
                );

                TimelineCalendarEvent::query()->updateOrCreate(
                    ['milestone_key' => $definition['timeline_key']],
                    [
                        'label' => $definition['timeline_label'],
                        'term_label' => $semester,
                        'starts_at' => $startsAt,
                        'ends_at' => $dueAt,
                        'sort_order' => $definition['sort_order'],
                        'is_active' => true,
                    ],
                );

                $manualStates[$key] = $manualStatus->value;
            }

            // Retain the same semester label on unmapped active milestones such as a configured revision period.
            TimelineCalendarEvent::query()
                ->where('is_active', true)
                ->update(['term_label' => $semester]);

            $this->auditLog->record($actor, 'settings.deadlines_updated', $actor, [
                'semester_label' => $semester,
                'processes' => array_keys($attributes['processes']),
                'manual_states' => $manualStates,
                'result' => 'updated',
            ]);
        }, 3);
    }

    /**
     * Preserve legacy automatic display state until the RES Lead saves an explicit override.
     */
    private function isOpenForDisplay(DeadlineConfiguration $configuration): bool
    {
        return match ($configuration->manual_status) {
            DeadlineManualStatus::Open => true,
            DeadlineManualStatus::Closed => false,
            default => ! $configuration->starts_at?->isFuture() && ! $configuration->due_at->isPast(),
        };
    }
}
