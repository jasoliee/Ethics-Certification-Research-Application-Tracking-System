<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Exceptions\CertificateGenerationException;
use App\Models\ApplicationDecisionRelease;
use App\Models\Certificate;
use App\Models\CertificateBackground;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\Certificates\ApplicantCertificateService;
use App\Services\Certificates\CertificateBackgroundService;
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

    public function test_a_new_background_affects_only_future_certificate_versions(): void
    {
        Storage::fake('local');
        Notification::fake();
        [, $resLead, $application] = $this->approvedApplication();
        $released = app(CertificateReleaseService::class)->release($resLead, $application);
        $firstVersion = $released['certificate']->currentVersion;
        $officialBackgroundId = $firstVersion->certificate_background_id;

        $newBackground = app(CertificateBackgroundService::class)->uploadAndActivate(
            $resLead,
            UploadedFile::fake()->image('future-background.png', 596, 842),
        );
        $regenerated = app(CertificateReleaseService::class)->release($resLead, $application->refresh(), true);
        $secondVersion = $regenerated['certificate']->currentVersion;

        $this->assertSame('regenerated', $regenerated['action']);
        $this->assertNotSame($officialBackgroundId, $newBackground->id);
        $this->assertSame($officialBackgroundId, $firstVersion->refresh()->certificate_background_id);
        $this->assertSame($newBackground->id, $secondVersion->certificate_background_id);
        $this->assertSame(CertificateVersionStatus::Superseded, $firstVersion->status);
        $this->assertSame(2, $secondVersion->certificate_version);
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
