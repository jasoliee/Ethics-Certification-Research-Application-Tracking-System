<?php

namespace App\Services\Certificates;

use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Models\ApplicantSurveyResponse;
use App\Models\Certificate;
use App\Models\CertificateVersion;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApplicantCertificateService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /** @param array<string, mixed> $payload */
    public function submitSurvey(
        User $actor,
        ResearchApplication $application,
        array $payload,
    ): ApplicantSurveyResponse {
        return DB::transaction(function () use ($actor, $application, $payload): ApplicantSurveyResponse {
            $lockedApplication = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('submitSurvey', $lockedApplication);

            $certificate = Certificate::query()
                ->where('research_application_id', $lockedApplication->id)
                ->lockForUpdate()
                ->first();
            $version = $certificate?->current_certificate_version_id
                ? CertificateVersion::query()
                    ->whereKey($certificate->current_certificate_version_id)
                    ->where('certificate_id', $certificate->id)
                    ->lockForUpdate()
                    ->first()
                : null;

            if (! $certificate
                || ! in_array($certificate->status, [CertificateStatus::Released, CertificateStatus::Claimed], true)
                || ! $version
                || $version->status !== CertificateVersionStatus::Ready) {
                throw ValidationException::withMessages([
                    'survey' => 'The required evaluation becomes available after RES releases a generated certificate.',
                ])->errorBag('certificateSurvey');
            }

            $existing = ApplicantSurveyResponse::query()
                ->where('research_application_id', $lockedApplication->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $response = ApplicantSurveyResponse::create([
                'research_application_id' => $lockedApplication->id,
                'applicant_user_id' => $actor->id,
                'ratings' => $payload['ratings'],
                'positive_feedback' => trim((string) $payload['positive_feedback']),
                'improvement_feedback' => trim((string) $payload['improvement_feedback']),
                'additional_comments' => filled($payload['additional_comments'] ?? null)
                    ? trim((string) $payload['additional_comments'])
                    : null,
                'completed_at' => now(),
            ]);

            // Evaluation answers are intentionally excluded from audit metadata.
            $this->auditLog->record($actor, 'certificate.survey_completed', $lockedApplication, [
                'survey_response_id' => $response->id,
                'certificate_id' => $certificate->id,
                'certificate_version_id' => $version->id,
                'result' => 'completed',
            ]);

            return $response;
        }, 3);
    }

    public function claim(User $actor, ResearchApplication $application): Certificate
    {
        return DB::transaction(function () use ($actor, $application): Certificate {
            $lockedApplication = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('claimCertificate', $lockedApplication);

            $certificate = Certificate::query()
                ->where('research_application_id', $lockedApplication->id)
                ->lockForUpdate()
                ->first();
            $version = $certificate?->current_certificate_version_id
                ? CertificateVersion::query()
                    ->whereKey($certificate->current_certificate_version_id)
                    ->where('certificate_id', $certificate->id)
                    ->lockForUpdate()
                    ->first()
                : null;
            $survey = ApplicantSurveyResponse::query()
                ->where('research_application_id', $lockedApplication->id)
                ->where('applicant_user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $certificate
                || ! in_array($certificate->status, [CertificateStatus::Released, CertificateStatus::Claimed], true)
                || ! $certificate->released_at
                || ! $certificate->released_by_user_id
                || ! $version
                || $version->status !== CertificateVersionStatus::Ready
                || ! $survey) {
                throw ValidationException::withMessages([
                    'certificate' => 'This certificate is not claimable. Confirm RES release, successful generation, and survey completion.',
                ])->errorBag('certificateClaim');
            }

            if ($certificate->status === CertificateStatus::Claimed
                && $certificate->claimed_certificate_version_id === $version->id
                && $certificate->claimed_by_user_id === $actor->id) {
                return $certificate->load('currentVersion');
            }

            $claimedAt = now();
            $version->update([
                'claimed_by_user_id' => $actor->id,
                'claimed_at' => $claimedAt,
            ]);
            $certificate->update([
                'status' => CertificateStatus::Claimed->value,
                'claimed_by_user_id' => $actor->id,
                'claimed_certificate_version_id' => $version->id,
                'claimed_at' => $claimedAt,
            ]);

            $this->auditLog->record($actor, 'certificate.claimed', $lockedApplication, [
                'certificate_id' => $certificate->id,
                'certificate_version_id' => $version->id,
                'certificate_version' => $version->certificate_version,
                'result' => CertificateStatus::Claimed->value,
            ]);

            return $certificate->refresh()->load('currentVersion');
        }, 3);
    }
}
