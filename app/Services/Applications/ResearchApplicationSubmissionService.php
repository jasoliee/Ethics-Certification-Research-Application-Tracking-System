<?php

namespace App\Services\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResearchApplicationSubmissionService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function submit(User $actor, ResearchApplication $application): ResearchApplication
    {
        Gate::forUser($actor)->authorize('submit', $application);
        $required = DocumentRequirement::query()
            ->where('is_active', true)
            ->pluck('name', 'id');

        if ($required->isEmpty()) {
            throw ValidationException::withMessages(['requirements' => 'Application requirements are not configured yet.']);
        }

        $completedIds = ApplicationDocument::query()
            ->where('research_application_id', $application->id)
            ->where('is_current', true)
            ->where('validation_status', RequirementStatus::Completed->value)
            ->whereIn('document_requirement_id', $required->keys())
            ->distinct()
            ->pluck('document_requirement_id');
        $notReady = $required->except($completedIds->all());

        if ($notReady->isNotEmpty()) {
            throw ValidationException::withMessages([
                'requirements' => 'Complete every mandatory requirement before submission. Not ready: '.$notReady->values()->join(', ').'.',
            ]);
        }

        return DB::transaction(function () use ($actor, $application): ResearchApplication {
            $application->update([
                'application_status' => ApplicationStatus::SubmittedToAdviser->value,
                'submitted_at' => now(),
                'status_updated_at' => now(),
            ]);

            $this->auditLog->record($actor, 'application.submitted', $application, [
                'result' => 'submitted_to_adviser',
            ]);

            return $application->refresh();
        });
    }
}
