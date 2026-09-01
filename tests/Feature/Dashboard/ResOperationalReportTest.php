<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AcademicTermStatus;
use App\Enums\ApplicantType;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateVersionStatus;
use App\Enums\ResearchType;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\ApplicationDecisionRelease;
use App\Models\Certificate;
use App\Models\CertificateVersion;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Services\Reports\OperationalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class ResOperationalReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reu_report_applies_validated_filters_and_renders_the_required_concise_sections(): void
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
            ->assertDontSee('Workflow Pipeline')
            ->assertSee('Filtered Application List')
            ->assertSee('Applicant Certification')
            ->assertSee('Reviewer Review Workload')
            ->assertSee('Adviser Endorsement Workload')
            ->assertSee('View All')
            ->assertDontSee('Application Submission Trend')
            ->assertDontSee('Turnaround Time')
            ->assertDontSee('Reviewer Capacity and Delay')
            ->assertDontSee('Action Required')
            ->assertDontSee('Certificate Follow-up')
            ->assertDontSee('Operations and Data Quality')
            ->assertDontSee('Delayed');
        $report = $response->viewData('report');
        $this->assertSame(1, $report['summary']['submitted']);
        $this->assertTrue($report['has_data']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $report['applications']);
        $this->assertSame(10, $report['applications']->perPage());
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

    public function test_filtered_application_list_paginates_at_ten_and_view_all_preserves_filters(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $matching = ResearchApplication::factory()->count(12)->create([
            'institution' => 'Pagination Institute',
            'research_type' => ResearchType::Thesis,
            'submitted_at' => now()->subDay(),
        ]);
        $outside = ResearchApplication::factory()->create([
            'institution' => 'Outside Institute',
            'research_type' => ResearchType::Thesis,
            'submitted_at' => now()->subDay(),
        ]);
        $filters = [
            'institute' => 'Pagination Institute',
            'research_type' => ResearchType::Thesis->value,
        ];

        $firstPage = $this->actingAs($resLead)->get(route('res.reports.index', $filters));
        $paginator = $firstPage->viewData('report')['applications'];
        $firstPage->assertOk()
            ->assertSee('View All')
            ->assertSee(route('res.reports.applications.index', [
                'research_type' => ResearchType::Thesis->value,
                'institute' => 'Pagination Institute',
            ]))
            ->assertSee(route('res.applications.show', $matching->last()), false)
            ->assertDontSee($outside->application_code);
        $this->assertSame(10, $paginator->count());
        $this->assertSame(12, $paginator->total());

        $secondPage = $this->actingAs($resLead)->get(route('res.reports.index', [
            ...$filters,
            'applications_page' => 2,
        ]));
        $this->assertSame(2, $secondPage->viewData('report')['applications']->count());

        $all = $this->actingAs($resLead)->get(route('res.reports.applications.index', $filters));
        $all->assertOk()
            ->assertSee($matching->last()->application_code)
            ->assertDontSee($outside->application_code);
        $this->assertCount(12, $all->viewData('applications'));
        $this->assertEquals($filters, $all->viewData('filters'));
    }

    public function test_institute_applicant_list_only_links_to_certificate_released_applications(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $institute = 'Double Blind Institute';
        $hiddenApplicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'institution' => $institute,
            'name' => 'Hidden Until Release',
        ]);
        $releasedApplicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'institution' => $institute,
            'name' => 'Released Applicant',
        ]);
        $hiddenApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $hiddenApplicant->id,
            'institution' => $institute,
            'submitted_at' => now()->subDay(),
        ]);
        $releasedApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $releasedApplicant->id,
            'institution' => $institute,
            'application_status' => ApplicationStatus::CertificateReleased,
            'submitted_at' => now()->subDay(),
        ]);
        $releasedApplication->certificateRecipients()->delete();
        $recipient = $releasedApplication->certificateRecipients()->create([
            'recipient_name' => $releasedApplicant->name,
            'normalized_name' => mb_strtolower($releasedApplicant->name),
            'sort_order' => 1,
        ]);
        ApplicationDecisionRelease::create([
            'research_application_id' => $releasedApplication->id,
            'review_cycle' => 0,
            'source_review_type' => 'initial_review',
            'decision' => ReviewDecision::Approved,
            'released_by_user_id' => $resLead->id,
            'released_at' => now()->subDay(),
        ]);
        Certificate::create([
            'research_application_id' => $releasedApplication->id,
            'application_certificate_recipient_id' => $recipient->id,
            'applicant_user_id' => $releasedApplicant->id,
            'recipient_name' => $releasedApplicant->name,
            'certificate_number' => 'RELEASED-INSTITUTE-CERTIFICATE',
            'status' => CertificateStatus::Released,
            'released_by_user_id' => $resLead->id,
            'released_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($resLead)->get(route('res.reports.institutes.applicants', [
            'institute' => $institute,
        ]));

        $response->assertOk()
            ->assertSee($hiddenApplicant->name)
            ->assertSee($releasedApplicant->name)
            ->assertDontSee(route('res.applications.show', $hiddenApplication), false)
            ->assertSee(route('res.applications.show', $releasedApplication), false);
        $rows = $response->viewData('rows')->keyBy(fn (array $row): int => $row['applicant']->id);
        $this->assertFalse($rows[$hiddenApplicant->id]['can_view']);
        $this->assertTrue($rows[$releasedApplicant->id]['can_view']);
    }

    public function test_claimed_and_unclaimed_metrics_count_distinct_applicant_accounts_not_certificate_rows(): void
    {
        $claimedApplicant = User::factory()->create(['institution' => 'Counting Institute']);
        $unclaimedApplicant = User::factory()->create(['institution' => 'Counting Institute']);
        $claimedApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $claimedApplicant->id,
            'institution' => 'Counting Institute',
            'submitted_at' => now()->subDay(),
        ]);
        $unclaimedApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $unclaimedApplicant->id,
            'institution' => 'Counting Institute',
            'submitted_at' => now()->subDay(),
        ]);

        foreach ([[$claimedApplication, $claimedApplicant, CertificateStatus::Claimed], [$unclaimedApplication, $unclaimedApplicant, CertificateStatus::Released]] as [$application, $applicant, $status]) {
            $application->certificateRecipients()->delete();
            foreach (range(1, 5) as $index) {
                $recipient = $application->certificateRecipients()->create([
                    'recipient_name' => "Recipient {$index}",
                    'normalized_name' => "recipient {$index}",
                    'sort_order' => $index,
                ]);
                Certificate::create([
                    'research_application_id' => $application->id,
                    'application_certificate_recipient_id' => $recipient->id,
                    'applicant_user_id' => $applicant->id,
                    'recipient_name' => $recipient->recipient_name,
                    'certificate_number' => $application->application_code.'-'.$index,
                    'status' => $status,
                    'released_at' => now()->subDay(),
                    'claimed_at' => $status === CertificateStatus::Claimed ? now()->subHour() : null,
                ]);
            }
        }

        $report = app(OperationalReportService::class)->report([]);
        $institute = $report['institute_summary']->firstWhere('institute', 'Counting Institute');

        $this->assertSame(1, $report['summary']['certificates_claimed']);
        $this->assertSame(1, $report['summary']['certificates_unclaimed']);
        $this->assertSame(1, $institute['claimed']);
        $this->assertSame(1, $institute['unclaimed']);
    }

    public function test_reviewer_workload_uses_only_the_current_active_term_and_fixed_capacity(): void
    {
        $current = AcademicTerm::create([
            'semester' => 'Current Term',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
            'status' => AcademicTermStatus::Active,
        ]);
        $historical = AcademicTerm::create([
            'semester' => 'Historical Term',
            'academic_year' => '2025-2026',
            'starts_at' => now()->subYear(),
            'ends_at' => now()->subMonths(9),
            'is_active' => false,
            'status' => AcademicTermStatus::Ended,
        ]);
        $reviewer = User::factory()->reviewer()->create();

        foreach (range(1, 2) as $index) {
            $application = ResearchApplication::factory()->create([
                'academic_term_id' => $current->id,
                'review_type' => ReviewType::Expedited,
                'submitted_at' => now()->subDays($index),
            ]);
            ReviewerAssignment::factory()->create([
                'research_application_id' => $application->id,
                'reviewer_user_id' => $reviewer->id,
                'assignment_status' => ReviewerAssignmentStatus::Pending,
            ]);
        }
        foreach (range(1, 3) as $index) {
            $application = ResearchApplication::factory()->create([
                'academic_term_id' => $historical->id,
                'review_type' => ReviewType::FullBoard,
                'submitted_at' => now()->subMonths(9)->subDays($index),
            ]);
            ReviewerAssignment::factory()->create([
                'research_application_id' => $application->id,
                'reviewer_user_id' => $reviewer->id,
                'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            ]);
        }

        $row = app(OperationalReportService::class)
            ->report(['academic_term_id' => $historical->id])['reviewer_workload']
            ->firstWhere(fn (array $item): bool => $item['reviewer']->is($reviewer));

        $this->assertSame(2, $row['total']);
        $this->assertSame(2, $row['expedited']);
        $this->assertSame(0, $row['full_board']);
        $this->assertSame(28, $row['remaining']);
    }

    public function test_print_download_and_survey_print_keep_the_current_filter_scope_and_updated_headers(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $matching = ResearchApplication::factory()->create([
            'application_code' => 'PRINT-FILTER-MATCH',
            'institution' => 'Print Institute',
            'submitted_at' => now()->subDay(),
        ]);
        $outside = ResearchApplication::factory()->create([
            'application_code' => 'PRINT-FILTER-OUTSIDE',
            'institution' => 'Outside Institute',
            'submitted_at' => now()->subDay(),
        ]);
        $filters = ['institute' => 'Print Institute'];

        $this->actingAs($resLead)->get(route('res.reports.print', $filters))
            ->assertOk()
            ->assertSee('ECRATS Research Ethics Unit Operational Report')
            ->assertSee('Filtered Records')
            ->assertSeeInOrder(['Filtered Records', 'Generated:'])
            ->assertSee('@page { size: A4 portrait; margin: 1in; }', false)
            ->assertSee($matching->application_code)
            ->assertDontSee($outside->application_code)
            ->assertDontSee('Filtered management report')
            ->assertDontSee('Privacy boundary:');

        $workbook = $this->actingAs($resLead)->get(route('res.reports.download', [...$filters, 'format' => 'xlsx']));
        $workbook->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith("PK\x03\x04", $workbook->getContent());
        $path = tempnam(sys_get_temp_dir(), 'ecrats-report-test-');
        file_put_contents($path, $workbook->getContent());
        try {
            $spreadsheet = (new XlsxReader)->load($path);
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                    $this->assertTrue($sheet->getStyle($coordinate)->getAlignment()->getWrapText(), "{$sheet->getTitle()}!{$coordinate} must wrap.");
                }
            }
            $spreadsheet->disconnectWorksheets();
        } finally {
            @unlink($path);
        }

        $this->actingAs($resLead)->get(route('res.reports.survey.print', $filters))
            ->assertOk()
            ->assertSeeInOrder(['Print Institute', 'Generated:'])
            ->assertSee('@page { size: A4 portrait; margin: 1in; }', false)
            ->assertSeeInOrder(['Responses', 'Average'])
            ->assertDontSee('Preserved legacy responses excluded')
            ->assertSee('Print Report');
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

    public function test_report_and_survey_pdf_downloads_use_valid_multi_page_pdf_outputs(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        ResearchApplication::factory()->count(3)->create(['submitted_at' => now()->subDay()]);

        foreach (['download' => 'res.reports.download', 'survey' => 'res.reports.survey.download'] as $name => $routeName) {
            $response = $this->actingAs($resLead)->get(route($routeName, ['format' => 'pdf']));
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $bytes = $response->getContent();
            $this->assertStringStartsWith('%PDF-', $bytes, $name.' download must be a genuine PDF.');

            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $bytes);
            rewind($stream);
            $parser = new Fpdi;
            $pageCount = $parser->setSourceFile(new StreamReader($stream));
            $this->assertGreaterThanOrEqual(1, $pageCount);
            foreach (range(1, $pageCount) as $page) {
                $template = $parser->importPage($page);
                $size = $parser->getTemplateSize($template);
                $this->assertEqualsWithDelta(210, $size['width'], 0.6);
                $this->assertEqualsWithDelta(297, $size['height'], 0.6);
            }
            fclose($stream);
        }
    }

    public function test_partial_recipient_certificate_sets_count_one_unclaimed_account_but_remain_identity_hidden(): void
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

        $this->assertSame(1, $report['summary']['certificates_unclaimed']);
        $this->assertTrue($report['applicant_certification']->isEmpty());
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
        $version = CertificateVersion::create([
            'certificate_id' => $certificates->first()->id,
            'certificate_version' => 1,
            'status' => CertificateVersionStatus::Ready,
            'stored_file_path' => 'certificates/privacy-report-001.pdf',
            'original_file_name' => 'privacy-report-001.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 256,
            'sha256' => hash('sha256', 'certificate'),
            'official_template_version' => 'test-v1',
            'official_template_sha256' => hash('sha256', 'template'),
            'background_sha256' => hash('sha256', 'background'),
            'generator_version' => 'test-generator',
            'generated_by_user_id' => $resLead->id,
            'generated_at' => now()->subHour(),
            'released_by_user_id' => $resLead->id,
            'released_at' => now()->subHour(),
        ]);
        $certificates->first()->update(['current_certificate_version_id' => $version->id]);

        $this->actingAs($resLead)
            ->get(route('res.reports.index'))
            ->assertOk()
            ->assertDontSee($applicant->name)
            ->assertDontSee($applicant->institutional_identifier);
        $this->actingAs($resLead)
            ->get(route('res.reports.applicants.show', $applicant))
            ->assertNotFound();
        $this->assertTrue(app(OperationalReportService::class)->report([])['applicant_certification']->isEmpty());
        $this->actingAs($resLead)->get(route('res.reports.download', ['format' => 'xlsx']))->assertOk();

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
            ->assertSee('PRIVACY-CERT-2')
            ->assertSee(route('res.certificates.versions.preview', [$certificates->first(), $version]), false);
        $certificationRows = app(OperationalReportService::class)->report([])['applicant_certification'];
        $this->assertCount(1, $certificationRows);
        $this->assertSame('Unclaimed', $certificationRows->first()['certificate_status']);
        $this->actingAs($resLead)->get(route('res.reports.download', ['format' => 'xlsx']))->assertOk();
    }
}
