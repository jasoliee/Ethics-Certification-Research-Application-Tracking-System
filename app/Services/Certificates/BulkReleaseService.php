<?php

namespace App\Services\Certificates;

use App\Enums\ApplicationStatus;
use App\Enums\BulkReleaseType;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\ReviewSubmission;
use App\Models\User;
use App\Services\Applications\ApplicationRevisionWorkflowService;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class BulkReleaseService
{
    public function __construct(
        private readonly ApplicationRevisionWorkflowService $decisions,
        private readonly CertificateReleaseService $certificates,
        private readonly CertificationEligibilityService $eligibility,
        private readonly AuditLogService $auditLog,
    ) {}

    /** @return array{certificate: int, decision: int, both: int} */
    public function eligibleCounts(User $actor): array
    {
        $this->authorize($actor);
        $counts = ['certificate' => 0, 'decision' => 0, 'both' => 0];

        $this->relevantApplications()
            ->with($this->eligibilityRelations())
            ->orderBy('research_applications.id')
            ->chunkById(100, function (Collection $applications) use (&$counts): void {
                foreach ($applications as $application) {
                    $certificateEligible = $this->isCertificateEligible($application);
                    $decisionEligible = $this->decisionSource($application) !== null;
                    $counts['certificate'] += (int) $certificateEligible;
                    $counts['decision'] += (int) $decisionEligible;
                    $counts['both'] += (int) ($certificateEligible || $decisionEligible);
                }
            }, 'research_applications.id', 'id');

        return $counts;
    }

    /**
     * @return array{
     *     release_type: string,
     *     eligible: int,
     *     successfully_released: int,
     *     already_released: int,
     *     ineligible: int,
     *     failed: int,
     *     failed_application_codes: array<int, string>
     * }
     */
    public function release(User $actor, BulkReleaseType $type): array
    {
        $this->authorize($actor);
        $startedAt = CarbonImmutable::now();
        $summary = [
            'release_type' => $type->value,
            'eligible' => 0,
            'successfully_released' => 0,
            'already_released' => 0,
            'ineligible' => 0,
            'failed' => 0,
            'failed_application_codes' => [],
        ];
        $affected = [
            'successfully_released' => [],
            'already_released' => [],
            'ineligible' => [],
            'failed' => [],
        ];

        $this->relevantApplications()
            ->with($this->eligibilityRelations())
            ->orderBy('research_applications.id')
            ->chunkById(50, function (Collection $applications) use ($actor, $type, &$summary, &$affected): void {
                foreach ($applications as $application) {
                    $classification = $this->classify($application, $type);
                    if ($classification === 'ineligible') {
                        $summary['ineligible']++;
                        $affected['ineligible'][] = $application->id;

                        continue;
                    }
                    if ($classification === 'already_released') {
                        $summary['already_released']++;
                        $affected['already_released'][] = $application->id;

                        continue;
                    }

                    $summary['eligible']++;
                    try {
                        $this->releaseApplication($actor, $application, $type);
                        $summary['successfully_released']++;
                        $affected['successfully_released'][] = $application->id;
                    } catch (Throwable $exception) {
                        report($exception);
                        $summary['failed']++;
                        $summary['failed_application_codes'][] = $application->application_code;
                        $affected['failed'][] = $application->id;
                    }
                }
            }, 'research_applications.id', 'id');

        $completedAt = CarbonImmutable::now();
        $this->auditLog->record($actor, 'release.bulk_completed', null, [
            'release_type' => $type->value,
            'release_type_label' => $type->label(),
            'started_at' => $startedAt->toIso8601String(),
            'completed_at' => $completedAt->toIso8601String(),
            'successfully_released_count' => $summary['successfully_released'],
            'already_released_count' => $summary['already_released'],
            'ineligible_count' => $summary['ineligible'],
            'failed_count' => $summary['failed'],
            'affected_application_ids' => array_map(
                fn (array $ids): array => array_slice($ids, 0, 500),
                $affected,
            ),
            'failed_application_codes' => array_slice($summary['failed_application_codes'], 0, 100),
        ]);

        return $summary;
    }

    private function releaseApplication(
        User $actor,
        ResearchApplication $application,
        BulkReleaseType $type,
    ): void {
        if (in_array($type, [BulkReleaseType::Decision, BulkReleaseType::Both], true)) {
            $source = $this->decisionSource($application);
            if ($source) {
                $this->decisions->releaseDecision($actor, $application, $source);
                $application->refresh();
            }
        }

        if (in_array($type, [BulkReleaseType::Certificate, BulkReleaseType::Both], true)
            && $this->isCertificateEligible($application)) {
            $this->certificates->release($actor, $application);
        }
    }

    private function classify(ResearchApplication $application, BulkReleaseType $type): string
    {
        $certificateReleased = $this->certificateAlreadyReleased($application);
        $decisionReleased = $this->decisionAlreadyReleased($application);
        $certificateEligible = $this->isCertificateEligible($application);
        $decisionEligible = $this->decisionSource($application) !== null;

        return match ($type) {
            BulkReleaseType::Certificate => $certificateEligible
                ? 'eligible'
                : ($certificateReleased ? 'already_released' : 'ineligible'),
            BulkReleaseType::Decision => $decisionEligible
                ? 'eligible'
                : ($decisionReleased ? 'already_released' : 'ineligible'),
            BulkReleaseType::Both => ($certificateEligible || $decisionEligible)
                ? 'eligible'
                : (($certificateReleased || $decisionReleased) ? 'already_released' : 'ineligible'),
        };
    }

    private function isCertificateEligible(ResearchApplication $application): bool
    {
        return ! $this->certificateAlreadyReleased($application)
            && $this->eligibility->isEligible($application);
    }

    private function certificateAlreadyReleased(ResearchApplication $application): bool
    {
        $certificate = $application->certificate;

        return $certificate !== null
            && in_array($certificate->status, [CertificateStatus::Released, CertificateStatus::Claimed], true)
            && $certificate->currentVersion?->status === CertificateVersionStatus::Ready;
    }

    private function decisionAlreadyReleased(ResearchApplication $application): bool
    {
        return $application->decisionReleases->isNotEmpty();
    }

    /**
     * Bulk decision release is deterministic only when every current required
     * Reviewer submitted the same decision. A split decision remains available
     * for the RES Lead to inspect and release explicitly from the read-only workspace.
     */
    private function decisionSource(ResearchApplication $application): ?ReviewSubmission
    {
        if ($application->application_status !== ApplicationStatus::ReviewSubmittedPendingRelease) {
            return null;
        }

        $cycle = max(0, ((int) $application->current_revision_cycle) - 1);
        $reviewType = $cycle === 0 ? 'initial_review' : 'revision_review';
        $assignments = $application->reviewerAssignments
            ->where('review_cycle', $cycle)
            ->where('review_type', $reviewType);

        if ($assignments->isEmpty() || $assignments->contains(
            fn (ReviewerAssignment $assignment): bool => $assignment->assignment_status !== ReviewerAssignmentStatus::DecisionSubmitted
                || $assignment->reviewSubmission?->status !== ReviewSubmissionStatus::Submitted
                || $assignment->reviewSubmission?->decision === null,
        )) {
            return null;
        }

        $decisions = $assignments
            ->map(fn (ReviewerAssignment $assignment): ?string => $assignment->reviewSubmission?->decision?->value)
            ->filter()
            ->unique();

        return $decisions->count() === 1 ? $assignments->first()->reviewSubmission : null;
    }

    private function relevantApplications(): Builder
    {
        return ResearchApplication::query()
            ->where(function (Builder $query): void {
                $query->whereIn('application_status', [
                    ApplicationStatus::ReviewSubmittedPendingRelease->value,
                    ApplicationStatus::ResultReleasedAccepted->value,
                    ApplicationStatus::Exempted->value,
                    ApplicationStatus::CertificateReleased->value,
                ])->orWhereHas('certificate')
                    ->orWhereHas('decisionReleases');
            });
    }

    /** @return array<int|string, mixed> */
    private function eligibilityRelations(): array
    {
        return [
            'certificate.currentVersion',
            'decisionReleases:id,research_application_id,review_cycle',
            'reviewerAssignments' => fn ($assignments) => $assignments
                ->current()
                ->with('reviewSubmission'),
        ];
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->role === UserRole::ResLead, 403);
    }
}
