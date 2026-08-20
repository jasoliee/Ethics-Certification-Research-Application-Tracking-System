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
use App\Support\ApplicantSurveyCatalog;
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
                ->whereIn('status', [CertificateStatus::Released->value, CertificateStatus::Claimed->value])
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

            $ratings = $this->validatedRatings($payload);
            $suggestions = $payload['suggestions_comments'] ?? null;
            if ($suggestions !== null && (! is_string($suggestions) || mb_strlen($suggestions) > 2000)) {
                throw ValidationException::withMessages([
                    'suggestions_comments' => 'Suggestions and comments must be text no longer than 2,000 characters.',
                ])->errorBag('certificateSurvey');
            }

            $response = ApplicantSurveyResponse::create([
                'research_application_id' => $lockedApplication->id,
                'applicant_user_id' => $actor->id,
                'questionnaire_version' => ApplicantSurveyCatalog::VERSION,
                'ratings' => $ratings,
                // Keep legacy non-null columns intact without storing duplicate free-text answers.
                'positive_feedback' => '',
                'improvement_feedback' => '',
                'additional_comments' => null,
                'suggestions_comments' => filled($suggestions)
                    ? trim($suggestions)
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

    /**
     * Enforce the same exact questionnaire contract for non-HTTP service callers.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    private function validatedRatings(array $payload): array
    {
        $submitted = $payload['ratings'] ?? null;
        if (! is_array($submitted)) {
            throw ValidationException::withMessages([
                'ratings' => 'All 10 evaluation ratings are required.',
            ])->errorBag('certificateSurvey');
        }

        $expectedKeys = ApplicantSurveyCatalog::questionKeys();
        $submittedKeys = array_keys($submitted);
        sort($expectedKeys);
        sort($submittedKeys);

        if ($submittedKeys !== $expectedKeys) {
            throw ValidationException::withMessages([
                'ratings' => 'Submit exactly the 10 current evaluation ratings.',
            ])->errorBag('certificateSurvey');
        }

        $ratings = [];
        foreach (ApplicantSurveyCatalog::questionKeys() as $key) {
            $rating = filter_var($submitted[$key], FILTER_VALIDATE_INT);
            if ($rating === false || $rating < 1 || $rating > 5) {
                throw ValidationException::withMessages([
                    "ratings.{$key}" => 'Each evaluation rating must be an integer from 1 to 5.',
                ])->errorBag('certificateSurvey');
            }

            $ratings[$key] = $rating;
        }

        return $ratings;
    }

    public function claim(User $actor, ResearchApplication $application): Certificate
    {
        return DB::transaction(function () use ($actor, $application): Certificate {
            $lockedApplication = ResearchApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('claimCertificate', $lockedApplication);

            $certificates = Certificate::query()
                ->where('research_application_id', $lockedApplication->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $survey = ApplicantSurveyResponse::query()
                ->where('research_application_id', $lockedApplication->id)
                ->where('applicant_user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            $versions = CertificateVersion::query()
                ->whereIn('id', $certificates->pluck('current_certificate_version_id')->filter())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $claimable = $certificates->isNotEmpty() && $certificates->every(function (Certificate $certificate) use ($versions): bool {
                $version = $versions->get($certificate->current_certificate_version_id);

                return in_array($certificate->status, [CertificateStatus::Released, CertificateStatus::Claimed], true)
                    && $certificate->released_at
                    && $certificate->released_by_user_id
                    && $version?->status === CertificateVersionStatus::Ready;
            });
            if (! $claimable || ! $survey) {
                throw ValidationException::withMessages([
                    'certificate' => 'These certificates are not claimable. Confirm RES release, successful generation, and survey completion.',
                ])->errorBag('certificateClaim');
            }

            $alreadyClaimed = $certificates->every(fn (Certificate $certificate): bool =>
                $certificate->status === CertificateStatus::Claimed
                && $certificate->claimed_certificate_version_id === $certificate->current_certificate_version_id
                && $certificate->claimed_by_user_id === $actor->id
            );
            if ($alreadyClaimed) {
                return $certificates->first()->load('currentVersion');
            }

            $claimedAt = now();
            foreach ($certificates as $certificate) {
                $version = $versions->get($certificate->current_certificate_version_id);
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
            }

            $this->auditLog->record($actor, 'certificate.claimed', $lockedApplication, [
                'certificate_ids' => $certificates->pluck('id')->all(),
                'certificate_version_ids' => $versions->keys()->all(),
                'recipient_count' => $certificates->count(),
                'result' => CertificateStatus::Claimed->value,
            ]);

            return $certificates->first()->refresh()->load('currentVersion');
        }, 3);
    }
}
