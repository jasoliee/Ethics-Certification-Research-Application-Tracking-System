<?php

namespace App\Services\Certificates;

use App\Enums\CertificateStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificateBackground;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

class CertificateBackgroundRegenerationService
{
    public function __construct(
        private readonly CertificateReleaseService $certificates,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @return array{
     *     total: int,
     *     regenerated: int,
     *     already_current: int,
     *     failed: int,
     *     failed_certificate_numbers: array<int, string>
     * }
     */
    public function regenerateActive(User $actor, CertificateBackground $background): array
    {
        abort_unless($actor->role === UserRole::ResLead, 403);
        $startedAt = CarbonImmutable::now();
        $summary = [
            'total' => 0,
            'regenerated' => 0,
            'already_current' => 0,
            'failed' => 0,
            'failed_certificate_numbers' => [],
        ];
        $affectedCertificateIds = [];
        $failedCertificateIds = [];

        Certificate::query()
            ->whereIn('status', [CertificateStatus::Released->value, CertificateStatus::Claimed->value])
            ->whereNotNull('current_certificate_version_id')
            ->orderBy('id')
            ->chunkById(25, function (Collection $certificates) use (
                $actor,
                $background,
                &$summary,
                &$affectedCertificateIds,
                &$failedCertificateIds,
            ): void {
                foreach ($certificates as $certificate) {
                    $summary['total']++;

                    try {
                        $result = $this->certificates->regenerateForBackground($actor, $certificate, $background);
                        if ($result['action'] === 'skipped') {
                            $summary['already_current']++;

                            continue;
                        }

                        $summary['regenerated']++;
                        $affectedCertificateIds[] = $certificate->id;
                    } catch (Throwable $exception) {
                        report($exception);
                        $summary['failed']++;
                        $summary['failed_certificate_numbers'][] = $certificate->certificate_number;
                        $failedCertificateIds[] = $certificate->id;
                    }
                }
            });

        $this->auditLog->record($actor, 'certificate.background_regeneration_completed', $background, [
            'background_id' => $background->id,
            'background_version' => $background->asset_version,
            'started_at' => $startedAt->toIso8601String(),
            'completed_at' => CarbonImmutable::now()->toIso8601String(),
            'active_certificate_count' => $summary['total'],
            'regenerated_count' => $summary['regenerated'],
            'already_current_count' => $summary['already_current'],
            'failed_count' => $summary['failed'],
            'regenerated_certificate_ids' => array_slice($affectedCertificateIds, 0, 500),
            'failed_certificate_ids' => array_slice($failedCertificateIds, 0, 500),
            'failed_certificate_numbers' => array_slice($summary['failed_certificate_numbers'], 0, 100),
        ]);

        return $summary;
    }
}
