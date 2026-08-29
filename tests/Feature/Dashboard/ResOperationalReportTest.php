<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicantType;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\ResearchType;
use App\Enums\ReviewDecision;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\ApplicationDecisionRelease;
use App\Models\Certificate;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\Reports\OperationalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResOperationalReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_report_applies_validated_filters_and_renders_every_management_section(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $current = AcademicTerm::create([
            'semester' => 'Current Operations Term',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(3),
            'is_active' => true,
        ]);
        $historical = AcademicTerm::create([
            'semester' => 'Historical Operations Term',
            'academic_year' => '2025-2026',
            'starts_at' => now()->subYear(),
            'ends_at' => now()->subMonths(7),
            'is_active' => false,
        ]);
        ResearchApplication::factory()->create([
            'academic_term_id' => $current->id,
            'application_code' => 'REPORT-CURRENT-001',
            'research_type' => ResearchType::Thesis,
            'applicant_type' => ApplicantType::Student,
            'review_type' => ReviewType::Expedited,
            'application_status' => ApplicationStatus::UnderExpeditedReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'institution' => 'Institute of Computing and Digital Innovation',
            'submitted_at' => now()->subDays(4),
        ]);
        ResearchApplication::factory()->create([
            'academic_term_id' => $historical->id,
            'application_code' => 'REPORT-HISTORICAL-002',
            'research_type' => ResearchType::Capstone,
            'applicant_type' => ApplicantType::Faculty,
            'review_type' => ReviewType::FullBoard,
            'application_status' => ApplicationStatus::UnderFullBoardReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'institution' => 'Institute of Behavioral Sciences',
            'submitted_at' => now()->subMonths(9),
        ]);

        $response = $this->actingAs($resLead)->get(route('res.reports.index', [
            'academic_term_id' => $current->id,
            'research_type' => ResearchType::Thesis->value,
            'institute' => 'Institute of Computing and Digital Innovation',
        ]));

        $response->assertOk()
            ->assertSeeInOrder([
                'Search',
                'Academic Term',
                'Institute',
                'Workflow Status',
                'Certificate Status',
                'Review Type',
                'Research Type',
                'Applicant Category',
                'Date From',
                'Date To',
            ])
            ->assertSeeInOrder([
                'Current - Current Operations Term, A.Y. 2026-2027',
                'Historical Operations Term, A.Y. 2025-2026',
            ])
            ->assertSee('Workflow Pipeline')
            ->assertSee('Application Submission Trend')
            ->assertSee('Turnaround Time')
            ->assertSee('Reviewer Capacity and Delay')
            ->assertSee('Adviser Endorsement Workload')
            ->assertSee('Action Required')
            ->assertSee('Certificate Follow-up')
            ->assertSee('Operations and Data Quality');
        $report = $response->viewData('report');
        $this->assertSame(1, $report['summary']['submitted']);
        $this->assertTrue($report['has_data']);
        $this->assertSame(
            6,
            collect($report['data_quality'])->firstWhere('label', 'Missing deadline configuration')['count'],
        );
    }

    public function test_reports_are_res_only_and_reject_unknown_term_ids_server_side(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);

        $unauthorized = $this->actingAs($applicant)->get(route('res.reports.index'));
        $unauthorized->status() === 403
            ? $unauthorized->assertForbidden()
            : $unauthorized->assertRedirect(route('dashboard'));

        $this->actingAs($resLead)
            ->get(route('res.reports.index', ['academic_term_id' => 999999]))
            ->assertSessionHasErrors('academic_term_id');
    }

    public function test_report_query_count_does_not_grow_with_reviewer_or_adviser_rows(): void
    {
        User::factory()->reviewer()->create();
        User::factory()->create(['role' => UserRole::Adviser]);
        $reports = app(OperationalReportService::class);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $reports->report([]);
        $baseline = count(DB::getQueryLog());

        User::factory()->reviewer()->count(8)->create();
        User::factory()->count(8)->create(['role' => UserRole::Adviser]);
        DB::flushQueryLog();
        $reports->report([]);
        $expanded = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($baseline + 1, $expanded);
    }

    public function test_partial_recipient_certificate_sets_are_followed_up_but_never_counted_complete(): void
    {
        $application = ResearchApplication::factory()->create([
            'application_status' => ApplicationStatus::ForCertificateRelease,
            'submitted_at' => now()->subDays(5),
        ]);
        $application->certificateRecipients()->delete();
        $recipients = collect(['First Recipient', 'Second Recipient'])->map(
            fn (string $name, int $index) => $application->certificateRecipients()->create([
                'recipient_name' => $name,
                'normalized_name' => mb_strtolower($name),
                'sort_order' => $index + 1,
            ]),
        );
        foreach ($recipients as $index => $recipient) {
            Certificate::create([
                'research_application_id' => $application->id,
                'application_certificate_recipient_id' => $recipient->id,
                'applicant_user_id' => $application->applicant_user_id,
                'recipient_name' => $recipient->recipient_name,
                'certificate_number' => 'REPORT-PARTIAL-'.($index + 1),
                'status' => $index === 0 ? CertificateStatus::Released : CertificateStatus::PendingRelease,
                'released_at' => $index === 0 ? now()->subDay() : null,
            ]);
        }

        $report = app(OperationalReportService::class)->report([]);

        $this->assertSame(0, $report['summary']['certificates_released']);
        $followUp = $report['certificate_follow_up']->firstWhere(
            fn (array $row): bool => $row['application']->is($application),
        );
        $this->assertSame(2, $followUp['recipient_count']);
        $this->assertSame('Partial Release', $followUp['status']);
        $this->assertSame('0 of 2 claimed', $followUp['claim_status']);
    }

    public function test_report_identity_and_drill_down_remain_hidden_until_approval_and_every_certificate_are_issued(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Privacy Boundary Applicant',
            'first_name' => 'Privacy',
            'last_name' => 'Applicant',
            'institutional_identifier' => 'KLD-STU-PRIVACY-001',
        ]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant->id,
            'application_code' => 'PRIVACY-REPORT-001',
            'application_status' => ApplicationStatus::ForCertificateRelease,
            'submitted_at' => now()->subDays(3),
        ]);
        $application->certificateRecipients()->delete();
        $recipients = collect(['Privacy Recipient One', 'Privacy Recipient Two'])->map(
            fn (string $name, int $index) => $application->certificateRecipients()->create([
                'recipient_name' => $name,
                'normalized_name' => mb_strtolower($name),
                'sort_order' => $index + 1,
            ]),
        );
        ApplicationDecisionRelease::create([
            'research_application_id' => $application->id,
            'review_cycle' => 0,
            'source_review_type' => 'initial_review',
            'decision' => ReviewDecision::Approved,
            'released_by_user_id' => $resLead->id,
            'released_at' => now()->subDay(),
        ]);
        $certificates = $recipients->map(fn ($recipient, int $index) => Certificate::create([
            'research_application_id' => $application->id,
            'application_certificate_recipient_id' => $recipient->id,
            'applicant_user_id' => $applicant->id,
            'recipient_name' => $recipient->recipient_name,
            'certificate_number' => 'PRIVACY-CERT-'.($index + 1),
            'status' => $index === 0 ? CertificateStatus::Released : CertificateStatus::PendingRelease,
            'released_at' => $index === 0 ? now()->subHour() : null,
        ]));

        $this->actingAs($resLead)
            ->get(route('res.reports.index'))
            ->assertOk()
            ->assertDontSee($applicant->name)
            ->assertDontSee($applicant->institutional_identifier);
        $this->actingAs($resLead)
            ->get(route('res.reports.applicants.show', $applicant))
            ->assertNotFound();
        $this->assertStringNotContainsString(
            $applicant->name,
            $this->actingAs($resLead)->get(route('res.reports.export'))->streamedContent(),
        );

        $certificates->last()->update([
            'status' => CertificateStatus::Released,
            'released_at' => now(),
        ]);

        $this->actingAs($resLead)
            ->get(route('res.reports.index'))
            ->assertOk()
            ->assertSee($applicant->name)
            ->assertSee($applicant->institutional_identifier);
        $this->actingAs($resLead)
            ->get(route('res.reports.applicants.show', $applicant))
            ->assertOk()
            ->assertSee($application->application_code)
            ->assertSee('PRIVACY-CERT-1')
            ->assertSee('PRIVACY-CERT-2');
        $this->assertStringContainsString(
            $applicant->name,
            $this->actingAs($resLead)->get(route('res.reports.export'))->streamedContent(),
        );
    }
}
