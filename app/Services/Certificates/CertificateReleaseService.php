<?php

namespace App\Services\Certificates;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\UserRole;
use App\Exceptions\CertificateGenerationException;
use App\Models\Certificate;
use App\Models\CertificateVersion;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CertificateReleaseService
{
    public function __construct(
        private readonly CertificationEligibilityService $eligibility,
        private readonly CertificateBackgroundService $backgrounds,
        private readonly OfficialCertificateGenerationService $generator,
        private readonly AuditLogService $auditLog,
    ) {}

    /** @return array{certificate: Certificate, action: string} */
    public function release(
        User $actor,
        ResearchApplication $application,
        bool $regenerate = false,
    ): array {
        // Authorize before resolving a background because initialization can write a
        // verified private asset and its provenance record.
        Gate::forUser($actor)->authorize('releaseCertificate', $application);
        $background = $this->backgrounds->active();
        $storedPath = null;
        $hadIssuedVersion = false;

        try {
            $result = DB::transaction(function () use (
                $actor,
                $application,
                $background,
                $regenerate,
                &$storedPath,
                &$hadIssuedVersion,
            ): array {
                $lockedApplication = ResearchApplication::query()
                    ->whereKey($application->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                Gate::forUser($actor)->authorize('releaseCertificate', $lockedApplication);

                $certificate = Certificate::query()
                    ->where('research_application_id', $lockedApplication->id)
                    ->lockForUpdate()
                    ->first();
                $currentVersion = $certificate?->current_certificate_version_id
                    ? CertificateVersion::query()
                        ->whereKey($certificate->current_certificate_version_id)
                        ->where('certificate_id', $certificate->id)
                        ->lockForUpdate()
                        ->first()
                    : null;
                $hadIssuedVersion = $currentVersion?->status === CertificateVersionStatus::Ready;

                if ($regenerate && ! $hadIssuedVersion) {
                    throw ValidationException::withMessages([
                        'certificate' => 'Only a successfully issued certificate can be regenerated.',
                    ])->errorBag('certificateRelease');
                }

                if (! $regenerate && $hadIssuedVersion
                    && in_array($certificate->status, [CertificateStatus::Released, CertificateStatus::Claimed], true)) {
                    return ['certificate' => $certificate->load('currentVersion'), 'action' => 'skipped'];
                }

                if (! $regenerate && ! $this->eligibility->isEligible($lockedApplication)) {
                    throw ValidationException::withMessages([
                        'certificate' => 'The application is not currently eligible for certificate release.',
                    ])->errorBag('certificateRelease');
                }

                if (! $certificate) {
                    $certificate = Certificate::create([
                        'research_application_id' => $lockedApplication->id,
                        'applicant_user_id' => $lockedApplication->applicant_user_id,
                        // The approved application code already follows the RES control-number pattern.
                        'certificate_number' => $lockedApplication->application_code,
                        'status' => CertificateStatus::PendingRelease->value,
                    ]);
                }

                $latestVersion = (int) CertificateVersion::query()
                    ->where('certificate_id', $certificate->id)
                    ->lockForUpdate()
                    ->max('certificate_version');
                $versionNumber = $latestVersion + 1;
                $releasedAt = now();
                $fileData = $this->generator->renderAndStore(
                    $actor,
                    $lockedApplication,
                    $certificate,
                    $background,
                    $versionNumber,
                    $releasedAt,
                );
                $storedPath = $fileData['stored_file_path'];

                $version = $certificate->versions()->create([
                    ...$fileData,
                    'certificate_version' => $versionNumber,
                    'status' => CertificateVersionStatus::Ready->value,
                ]);
                CertificateVersion::query()
                    ->where('certificate_id', $certificate->id)
                    ->whereKeyNot($version->id)
                    ->where('status', CertificateVersionStatus::Ready->value)
                    ->update(['status' => CertificateVersionStatus::Superseded->value]);

                $certificate->update([
                    'status' => CertificateStatus::Released->value,
                    'generation_failure_code' => null,
                    'current_certificate_version_id' => $version->id,
                    'released_by_user_id' => $actor->id,
                    'released_at' => $releasedAt,
                    // A regenerated version must be explicitly claimed; the prior version keeps its claim metadata.
                    'claimed_by_user_id' => null,
                    'claimed_certificate_version_id' => null,
                    'claimed_at' => null,
                ]);
                $lockedApplication->update([
                    'application_status' => ApplicationStatus::CertificateReleased->value,
                    'current_stage' => ApplicationStage::Completed->value,
                    'status_updated_at' => $releasedAt,
                ]);

                $this->auditLog->record(
                    $actor,
                    $regenerate ? 'certificate.regenerated' : 'certificate.released',
                    $lockedApplication,
                    [
                        'certificate_id' => $certificate->id,
                        'certificate_version_id' => $version->id,
                        'certificate_version' => $versionNumber,
                        'certificate_number' => $certificate->certificate_number,
                        'background_id' => $background->id,
                        'background_version' => $background->asset_version,
                        'file_sha256' => $version->sha256,
                        'result' => CertificateStatus::Released->value,
                    ],
                );

                return [
                    'certificate' => $certificate->refresh()->load('currentVersion'),
                    'action' => $regenerate ? 'regenerated' : 'released',
                ];
            }, 1);
        } catch (CertificateGenerationException $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            if (! $hadIssuedVersion) {
                $this->recordGenerationFailure($actor, $application, $exception->failureCode);
            }
            report($exception);

            throw ValidationException::withMessages([
                'certificate' => 'Certificate generation failed safely. No certificate was released; try again after checking the official template and active background.',
            ])->errorBag('certificateRelease');
        } catch (ValidationException $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            report($exception);

            throw ValidationException::withMessages([
                'certificate' => 'The certificate release request could not be completed safely.',
            ])->errorBag('certificateRelease');
        }

        if ($result['action'] !== 'skipped') {
            $application->applicant?->notify(new DashboardUpdateNotification([
                'title' => 'Certificate released',
                'message' => 'Your generated ethics certificate is ready after you complete the required evaluation and claim it.',
                'icon' => 'award',
                'tone' => 'green',
                'route' => 'applicant.revision-certificates.index',
                'route_parameters' => ['application' => $application->id],
            ]));
        }

        return $result;
    }

    /** @return array{eligible: int, released: int, skipped: int, failed: int, failures: array<int, string>} */
    public function releaseAllEligible(User $actor): array
    {
        abort_unless($actor->role === UserRole::ResLead, 403);

        $ids = ResearchApplication::query()
            ->whereIn('application_status', [
                ApplicationStatus::ResultReleasedAccepted->value,
                ApplicationStatus::Exempted->value,
            ])
            ->orderBy('id')
            ->pluck('id');
        $summary = [
            'eligible' => $ids->count(),
            'released' => 0,
            'skipped' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($ids as $id) {
            $application = ResearchApplication::query()->find($id);
            if (! $application) {
                $summary['skipped']++;

                continue;
            }

            try {
                $result = $this->release($actor, $application);
                $summary[$result['action'] === 'released' ? 'released' : 'skipped']++;
            } catch (Throwable) {
                $summary['failed']++;
                $summary['failures'][] = $application->application_code;
            }
        }

        $this->auditLog->record($actor, 'certificate.bulk_release_completed', null, [
            'eligible_count' => $summary['eligible'],
            'released_count' => $summary['released'],
            'skipped_count' => $summary['skipped'],
            'failed_count' => $summary['failed'],
            'failed_application_codes' => array_slice($summary['failures'], 0, 100),
        ]);

        return $summary;
    }

    private function recordGenerationFailure(
        User $actor,
        ResearchApplication $application,
        string $failureCode,
    ): void {
        DB::transaction(function () use ($actor, $application, $failureCode): void {
            $locked = ResearchApplication::query()->whereKey($application->id)->lockForUpdate()->first();
            if (! $locked || ! $this->eligibility->isEligible($locked)) {
                return;
            }

            $certificate = Certificate::query()
                ->where('research_application_id', $locked->id)
                ->lockForUpdate()
                ->first();
            if ($certificate?->current_certificate_version_id) {
                return;
            }

            $certificate ??= Certificate::create([
                'research_application_id' => $locked->id,
                'applicant_user_id' => $locked->applicant_user_id,
                'certificate_number' => $locked->application_code,
                'status' => CertificateStatus::GenerationFailed->value,
            ]);
            $certificate->update([
                'status' => CertificateStatus::GenerationFailed->value,
                'generation_failure_code' => mb_substr($failureCode, 0, 60),
            ]);
            $this->auditLog->record($actor, 'certificate.generation_failed', $locked, [
                'certificate_id' => $certificate->id,
                'failure_code' => mb_substr($failureCode, 0, 60),
                'result' => CertificateStatus::GenerationFailed->value,
            ]);
        }, 3);
    }
}
