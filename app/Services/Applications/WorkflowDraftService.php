<?php

namespace App\Services\Applications;

use App\Models\ResearchApplication;
use App\Models\User;
use App\Models\WorkflowDraft;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkflowDraftService
{
    public const SCREENING = 'res_screening';

    public function __construct(private readonly AuditLogService $auditLog) {}

    /** @param array<string, mixed> $payload */
    public function saveScreening(User $actor, ResearchApplication $application, array $payload): WorkflowDraft
    {
        return DB::transaction(function () use ($actor, $application, $payload): WorkflowDraft {
            $locked = ResearchApplication::query()->lockForUpdate()->findOrFail($application->id);
            $ability = $locked->screening()->exists() ? 'updateScreening' : 'classify';
            Gate::forUser($actor)->authorize($ability, $locked);

            $draft = WorkflowDraft::query()->updateOrCreate(
                [
                    'user_id' => $actor->id,
                    'research_application_id' => $locked->id,
                    'workflow' => self::SCREENING,
                ],
                ['payload' => [
                    'review_type' => $payload['review_type'] ?? null,
                    'classification_reason' => $payload['classification_reason'] ?? null,
                ]],
            );

            if ($draft->wasRecentlyCreated || $draft->wasChanged('payload')) {
                $this->auditLog->record($actor, 'application.res_screening_draft_saved', $locked, [
                    'workflow' => self::SCREENING,
                    'fields_present' => array_values(array_keys(array_filter(
                        $draft->payload,
                        fn (mixed $value): bool => $value !== null && $value !== '',
                    ))),
                ]);
            }

            return $draft;
        });
    }

    /** @return array<string, mixed> */
    public function screeningPayload(User $actor, ResearchApplication $application): array
    {
        $draft = WorkflowDraft::query()
            ->where('user_id', $actor->id)
            ->where('research_application_id', $application->id)
            ->where('workflow', self::SCREENING)
            ->first();

        return $draft?->payload ?? [];
    }

    public static function clearScreening(User $actor, ResearchApplication $application): void
    {
        WorkflowDraft::query()
            ->where('user_id', $actor->id)
            ->where('research_application_id', $application->id)
            ->where('workflow', self::SCREENING)
            ->delete();
    }
}
