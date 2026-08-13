<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\BulkReleaseType;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Exceptions\CertificateGenerationException;
use App\Models\ApplicationDecisionRelease;
use App\Models\Certificate;
use App\Models\CertificateBackground;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\Certificates\ApplicantCertificateService;
use App\Services\Certificates\BulkReleaseService;
use App\Services\Certificates\CertificateReleaseService;
use App\Services\Certificates\OfficialCertificateGenerationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CertificateReleaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_survey_claim_and_private_download_form_one_server_gated_sequence(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, $resLead, $application] = $this->approvedApplication();

        $result = app(CertificateReleaseService::class)->release($resLead, $application);
        $certificate = $result['certificate'];
        $version = $certificate->currentVersion;

        $this->assertSame('released', $result['action']);
        $this->assertSame(CertificateStatus::Released, $certificate->status);
        $this->assertSame(CertificateVersionStatus::Ready, $version->status);
        $this->assertSame($application->application_code, $certificate->certificate_number);
        Storage::disk('local')->assertExists($version->stored_file_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($version->stored_file_path));

        try {
            app(ApplicantCertificateService::class)->claim($applicant, $application);
            $this->fail('Claiming without the evaluation must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('certificate', $exception->errors());
        }

        app(ApplicantCertificateService::class)->submitSurvey($applicant, $application, $this->surveyPayload());
        $claimed = app(ApplicantCertificateService::class)->claim($applicant, $application);

        $this->assertSame(CertificateStatus::Claimed, $claimed->status);
        $this->assertSame($version->id, $claimed->claimed_certificate_version_id);
        $preview = $this->actingAs($applicant)
            ->get(route('applicant.revision-certificates.certificate.preview', [$application, $claimed, $version]))
            ->assertOk();
        $this->assertStringContainsString('private', (string) $preview->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Applicant]))
            ->get(route('applicant.revision-certificates.certificate.download', [$application, $claimed, $version]))
            ->assertForbidden();

        $repeat = app(CertificateReleaseService::class)->release($resLead, $application->refresh());
        $this->assertSame('skipped', $repeat['action']);
        $this->assertSame(1, $claimed->versions()->count());
    }

    public function test_http_evaluation_validation_is_visible_and_a_valid_submission_can_claim_immediately(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, $resLead, $application] = $this->approvedApplication('HTTPCLAIM');
        $released = app(CertificateReleaseService::class)->release($resLead, $application);
        $certificate = $released['certificate'];
        $version = $certificate->currentVersion;
        $indexUrl = route('applicant.revision-certificates.index', ['application' => $application->id]);
        $invalidPayload = $this->surveyPayload();
        $invalidPayload['positive_feedback'] = 'x';
        $invalidPayload['improvement_feedback'] = 'y';

        $invalidResponse = $this->actingAs($applicant)
            ->from($indexUrl)
            ->post(route('applicant.revision-certificates.survey.store', $application), $invalidPayload);

        $invalidResponse
            ->assertRedirect($indexUrl)
            ->assertSessionHasErrorsIn('certificateSurvey', [
                'positive_feedback',
                'improvement_feedback',
            ]);
        $this->actingAs($applicant)
            ->followingRedirects()
            ->from($indexUrl)
            ->post(route('applicant.revision-certificates.survey.store', $application), $invalidPayload)
            ->assertOk()
            ->assertSee('Evaluation was not submitted.')
            ->assertSee('must be at least 5 characters')
            ->assertSee('minlength="5"', false);
        $this->assertDatabaseMissing('applicant_survey_responses', [
            'research_application_id' => $application->id,
        ]);

        $this->actingAs($applicant)
            ->from($indexUrl)
            ->post(route('applicant.revision-certificates.survey.store', $application), $this->surveyPayload())
            ->assertRedirect($indexUrl)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Evaluation completed. Your released certificate is now ready to claim.');
        $this->assertDatabaseHas('applicant_survey_responses', [
            'research_application_id' => $application->id,
            'applicant_user_id' => $applicant->id,
        ]);
        $this->actingAs($applicant)
            ->get($indexUrl)
            ->assertOk()
            ->assertSee('Certificate ready to claim')
            ->assertSee(route('applicant.revision-certificates.certificate.claim', $application), false);

        $this->actingAs($applicant)
            ->from($indexUrl)
            ->post(route('applicant.revision-certificates.certificate.claim', $application))
            ->assertRedirect($indexUrl)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Certificate claimed. You may now view or download the current version.');
        $this->assertDatabaseHas('certificates', [
            'id' => $certificate->id,
            'status' => CertificateStatus::Claimed->value,
            'claimed_by_user_id' => $applicant->id,
            'claimed_certificate_version_id' => $version->id,
        ]);
        $this->assertDatabaseHas('certificate_versions', [
            'id' => $version->id,
            'claimed_by_user_id' => $applicant->id,
        ]);
    }

    public function test_background_change_regenerates_claimed_certificate_without_changing_historical_dates_or_claim(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, $resLead, $application] = $this->approvedApplication();
        $this->travelTo('2026-08-01 09:00:00');
        $released = app(CertificateReleaseService::class)->release($resLead, $application);
        app(ApplicantCertificateService::class)->submitSurvey($applicant, $application, $this->surveyPayload());
        $claimed = app(ApplicantCertificateService::class)->claim($applicant, $application);
        $firstVersion = $released['certificate']->currentVersion;
        $officialBackgroundId = $firstVersion->certificate_background_id;
        $originalIssuedAt = $firstVersion->generated_at->toDateTimeString();
        $originalReleasedAt = $claimed->released_at->toDateTimeString();
        $originalClaimedAt = $claimed->claimed_at->toDateTimeString();

        $this->travelTo('2026-08-13 14:30:00');
        $this->actingAs($resLead)
            ->post(route('res.certificate-backgrounds.store'), [
                'background' => UploadedFile::fake()->image('active-background.png', 596, 842),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('background_regeneration_summary', fn (array $summary): bool => $summary['regenerated'] === 1
                && $summary['failed'] === 0);

        $newBackground = CertificateBackground::query()->where('is_active', true)->firstOrFail();
        $claimed->refresh()->load('currentVersion');
        $secondVersion = $claimed->currentVersion;

        $this->assertNotSame($officialBackgroundId, $newBackground->id);
        $this->assertSame($officialBackgroundId, $firstVersion->refresh()->certificate_background_id);
        $this->assertSame($newBackground->id, $secondVersion->certificate_background_id);
        $this->assertSame(CertificateVersionStatus::Superseded, $firstVersion->status);
        $this->assertSame(2, $secondVersion->certificate_version);
        $this->assertSame($originalIssuedAt, $secondVersion->generated_at->toDateTimeString());
        $this->assertSame($originalReleasedAt, $secondVersion->released_at->toDateTimeString());
        $this->assertSame('2026-08-13 14:30:00', $secondVersion->regenerated_at->toDateTimeString());
        $this->assertSame('background_update', $secondVersion->regeneration_reason);
        $this->assertSame(CertificateStatus::Claimed, $claimed->status);
        $this->assertSame($originalReleasedAt, $claimed->released_at->toDateTimeString());
        $this->assertSame($originalClaimedAt, $claimed->claimed_at->toDateTimeString());
        $this->assertSame($secondVersion->id, $claimed->claimed_certificate_version_id);
        $this->assertSame($applicant->id, $secondVersion->claimed_by_user_id);
        Storage::disk('local')->assertExists($firstVersion->stored_file_path);
        Storage::disk('local')->assertExists($secondVersion->stored_file_path);
        Notification::assertSentToTimes($applicant, DashboardUpdateNotification::class, 1);
    }

    public function test_failed_background_regeneration_retains_the_last_valid_certificate_version(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, $resLead, $application] = $this->approvedApplication('BACKGROUND-FAIL');
        $released = app(CertificateReleaseService::class)->release($resLead, $application);
        $certificate = $released['certificate'];
        $originalVersion = $certificate->currentVersion;
        $originalReleasedAt = $certificate->released_at->toDateTimeString();

        $generator = $this->mock(OfficialCertificateGenerationService::class);
        $generator->shouldReceive('renderAndStore')->once()->andThrow(
            new CertificateGenerationException('Background rendering failed.', 'background_render_failed'),
        );

        $this->actingAs($resLead)
            ->post(route('res.certificate-backgrounds.store'), [
                'background' => UploadedFile::fake()->image('failing-background.png', 596, 842),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('background_regeneration_summary', fn (array $summary): bool => $summary['regenerated'] === 0
                && $summary['failed'] === 1
                && $summary['failed_certificate_numbers'] === [$certificate->certificate_number]);

        $certificate->refresh();
        $this->assertSame($originalVersion->id, $certificate->current_certificate_version_id);
        $this->assertSame(CertificateVersionStatus::Ready, $originalVersion->refresh()->status);
        $this->assertSame(1, $certificate->versions()->count());
        $this->assertSame($originalReleasedAt, $certificate->released_at->toDateTimeString());
        $this->assertSame(ApplicationStatus::CertificateReleased, $application->refresh()->application_status);
        Storage::disk('local')->assertExists($originalVersion->stored_file_path);
        Notification::assertSentToTimes($applicant, DashboardUpdateNotification::class, 1);
    }

    public function test_generation_failure_records_a_safe_state_without_releasing_a_file(): void
    {
        Storage::fake('local');
        Notification::fake();
        [, $resLead, $application] = $this->approvedApplication();
        $generator = $this->mock(OfficialCertificateGenerationService::class);
        $generator->shouldReceive('renderAndStore')->once()->andThrow(
            new CertificateGenerationException('Template integrity failed.', 'template_hash_mismatch'),
        );

        try {
            app(CertificateReleaseService::class)->release($resLead, $application);
            $this->fail('A failed generator must not release a certificate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('certificate', $exception->errors());
        }

        $certificate = Certificate::query()->where('research_application_id', $application->id)->firstOrFail();
        $this->assertSame(CertificateStatus::GenerationFailed, $certificate->status);
        $this->assertSame('template_hash_mismatch', $certificate->generation_failure_code);
        $this->assertNull($certificate->current_certificate_version_id);
        $this->assertNull($certificate->released_at);
        $this->assertSame(0, $certificate->versions()->count());
        $this->assertSame(ApplicationStatus::ResultReleasedAccepted, $application->refresh()->application_status);
    }

    public function test_bulk_release_reports_each_eligible_application_independently(): void
    {
        Storage::fake('local');
        Notification::fake();
        [, $resLead, $first] = $this->approvedApplication('BULK01');
        [, , $second] = $this->approvedApplication('BULK02', $resLead);

        $summary = app(CertificateReleaseService::class)->releaseAllEligible($resLead);

        $this->assertSame(2, $summary['eligible']);
        $this->assertSame(2, $summary['released']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(CertificateStatus::Released, $first->certificate()->firstOrFail()->status);
        $this->assertSame(CertificateStatus::Released, $second->certificate()->firstOrFail()->status);
    }

    public function test_typed_bulk_release_handles_both_idempotently_and_reports_all_outcomes(): void
    {
        Storage::fake('local');
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        [$newApplicant, $unanimous] = $this->pendingReviewedApplication('BULK-BOTH', [ReviewDecision::Approved]);
        [, , $already] = $this->approvedApplication('BULK-ALREADY', $resLead);
        app(CertificateReleaseService::class)->release($resLead, $already);
        [, $split] = $this->pendingReviewedApplication('BULK-SPLIT', [
            ReviewDecision::Approved,
            ReviewDecision::Disapproved,
        ]);

        $service = app(BulkReleaseService::class);
        $this->assertSame([
            'certificate' => 0,
            'decision' => 1,
            'both' => 1,
        ], $service->eligibleCounts($resLead));

        $summary = $service->release($resLead, BulkReleaseType::Both);

        $this->assertSame(1, $summary['eligible']);
        $this->assertSame(1, $summary['successfully_released']);
        $this->assertSame(1, $summary['already_released']);
        $this->assertSame(1, $summary['ineligible']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(CertificateStatus::Released, $unanimous->certificate()->firstOrFail()->status);
        $this->assertSame(ApplicationStatus::ReviewSubmittedPendingRelease, $split->refresh()->application_status);

        $repeat = $service->release($resLead, BulkReleaseType::Both);
        $this->assertSame(0, $repeat['successfully_released']);
        $this->assertSame(2, $repeat['already_released']);
        $this->assertSame(1, $repeat['ineligible']);
        $this->assertSame(1, $unanimous->certificate()->firstOrFail()->versions()->count());
        Notification::assertSentToTimes($newApplicant, DashboardUpdateNotification::class, 2);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'release.bulk_completed',
            'actor_user_id' => $resLead->id,
        ]);
    }

    public function test_unauthorized_release_cannot_initialize_background_or_certificate_state(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, , $application] = $this->approvedApplication();

        try {
            app(CertificateReleaseService::class)->release($applicant, $application);
            $this->fail('Only an RES Lead may initialize certificate release state.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(0, CertificateBackground::query()->count());
        $this->assertSame(0, Certificate::query()->count());
    }

    /** @return array{User, User, ResearchApplication} */
    private function approvedApplication(string $suffix = 'CERT01', ?User $resLead = null): array
    {
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'ALEXA M. RESEARCHER',
        ]);
        $resLead ??= User::factory()->create(['role' => UserRole::ResLead]);
        $application = ResearchApplication::factory()->create([
            'application_code' => 'RES-2026-S-ICDI-08112026-'.$suffix,
            'applicant_user_id' => $applicant->id,
            'research_title' => 'A Community-Based Study of Ethical Digital Service Delivery',
            'application_status' => ApplicationStatus::ResultReleasedAccepted,
            'current_stage' => ApplicationStage::DecisionRelease,
            'review_type' => 'expedited',
            'submitted_at' => now()->subMonth(),
        ]);
        ApplicationDecisionRelease::create([
            'research_application_id' => $application->id,
            'review_cycle' => 0,
            'source_review_type' => 'initial_review',
            'decision' => ReviewDecision::Approved,
            'released_by_user_id' => $resLead->id,
            'released_at' => now()->subDay(),
        ]);

        return [$applicant, $resLead, $application];
    }

    /** @param array<int, ReviewDecision> $decisions
     * @return array{User, ResearchApplication}
     */
    private function pendingReviewedApplication(string $suffix, array $decisions): array
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'application_code' => 'RES-2026-S-ICDI-08132026-'.$suffix,
            'applicant_user_id' => $applicant->id,
            'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease,
            'current_stage' => ApplicationStage::DecisionRelease,
            'review_type' => count($decisions) > 1 ? 'full_board' : 'expedited',
            'current_revision_cycle' => 1,
            'submitted_at' => now()->subWeek(),
        ]);

        foreach ($decisions as $sequence => $decision) {
            $assignment = ReviewerAssignment::factory()->create([
                'research_application_id' => $application->id,
                'reviewer_user_id' => User::factory()->create(['role' => UserRole::Reviewer])->id,
                'review_type' => 'initial_review',
                'review_cycle' => 0,
                'assignment_sequence' => $sequence + 1,
                'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
                'submitted_at' => now()->subDay(),
            ]);
            $assignment->reviewSubmission()->create([
                'status' => ReviewSubmissionStatus::Submitted,
                'decision' => $decision,
                'decision_comment' => 'Complete submitted Reviewer decision.',
                'submitted_at' => now()->subDay(),
            ]);
        }

        return [$applicant, $application];
    }

    /** @return array<string, mixed> */
    private function surveyPayload(): array
    {
        return [
            'ratings' => [
                'overall_process' => 5,
                'communication' => 4,
                'comments_helpfulness' => 5,
                'timeliness' => 4,
            ],
            'positive_feedback' => 'The released guidance was clear and useful.',
            'improvement_feedback' => 'Add more progress updates during review.',
            'additional_comments' => 'Thank you.',
        ];
    }
}
