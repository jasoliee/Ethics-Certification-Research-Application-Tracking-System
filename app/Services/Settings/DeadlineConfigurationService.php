<?php

namespace App\Services\Settings;

use App\Enums\DeadlineManualStatus;
use App\Models\AcademicTerm;
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
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly AcademicTermResolver $terms,
    ) {}

    /**
     * Return one display record per approved process, preferring canonical settings rows.
     *
     * @return array<string, array<string, mixed>>
     */
    public function settings(?AcademicTerm $term = null): array
    {
        $definitions = DeadlineProcessCatalog::definitions();
        $term ??= $this->terms->latestConfigured();
        $rowsQuery = DeadlineConfiguration::query()
            ->where('is_active', true)
            ->where(function ($query) use ($definitions): void {
                foreach (array_keys($definitions) as $index => $key) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('deadline_key', 'like', "%{$key}");
                }
            });

        if ($term) {
            $rowsQuery->where('academic_term_id', $term->id);
        } else {
            $rowsQuery->whereNull('academic_term_id');
        }

        $rows = $rowsQuery
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->get();

        return collect($definitions)->mapWithKeys(function (array $definition, string $key) use ($rows): array {
            $configuration = $rows->first(
                fn (DeadlineConfiguration $row): bool => DeadlineProcessCatalog::keyForDeadlineKey($row->deadline_key) === $key,
            );

            return [$key => [
                ...$definition,
                'key' => $key,
                'configuration' => $configuration,
                'is_open' => $configuration
                    ? $this->isEnabledForDisplay($configuration)
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
            $semester = trim((string) $attributes['semester']);
            $academicYear = trim((string) $attributes['academic_year']);
            $term = AcademicTerm::query()->updateOrCreate(
                [
                    'semester' => $semester,
                    'academic_year' => $academicYear,
                ],
                [
                    'starts_at' => Carbon::createFromFormat('Y-m-d', $attributes['term_starts_on'])->startOfDay(),
                    'ends_at' => Carbon::createFromFormat('Y-m-d', $attributes['term_ends_on'])->endOfDay(),
                    'is_active' => true,
                ],
            );
            $termLabel = $term->label();
            $manualStates = [];

            foreach (DeadlineProcessCatalog::definitions() as $key => $definition) {
                $process = $attributes['processes'][$key];
                $dueAt = Carbon::createFromFormat('Y-m-d\TH:i', $process['due_at']);
                $startsAt = $definition['exact_date']
                    ? $dueAt->copy()
                    : Carbon::createFromFormat('Y-m-d\TH:i', $process['starts_at']);
                $manualStatus = $process['is_open']
                    ? DeadlineManualStatus::Open
                    : null;

                DeadlineConfiguration::query()->updateOrCreate(
                    ['deadline_key' => "term-{$term->id}-{$key}"],
                    [
                        'academic_term_id' => $term->id,
                        'title' => $definition['timeline_label'],
                        'audience_role' => $definition['audience_role'],
                        'semester_label' => $termLabel,
                        'starts_at' => $startsAt,
                        'due_at' => $dueAt,
                        'manual_status' => $manualStatus,
                        'priority' => 100,
                        'is_active' => true,
                    ],
                );

                TimelineCalendarEvent::query()->updateOrCreate(
                    ['milestone_key' => "term-{$term->id}-{$definition['timeline_key']}"],
                    [
                        'academic_term_id' => $term->id,
                        'label' => $definition['timeline_label'],
                        'term_label' => $termLabel,
                        'starts_at' => $startsAt,
                        'ends_at' => $dueAt,
                        'sort_order' => $definition['sort_order'],
                        'is_active' => true,
                    ],
                );

                $manualStates[$key] = $manualStatus?->value ?? 'automatic';
            }

            $this->auditLog->record($actor, 'settings.deadlines_updated', $actor, [
                'semester' => $semester,
                'academic_year' => $academicYear,
                'term_starts_on' => $term->starts_at,
                'term_ends_on' => $term->ends_at,
                'processes' => array_keys($attributes['processes']),
                'manual_states' => $manualStates,
                'result' => 'updated',
            ], $term);
        }, 3);
    }

    /**
     * Show the configured toggle independently from whether its date window is currently open.
     */
    private function isEnabledForDisplay(DeadlineConfiguration $configuration): bool
    {
        return match ($configuration->manual_status) {
            DeadlineManualStatus::Open => true,
            DeadlineManualStatus::Closed => false,
            default => false,
        };
    }
}
