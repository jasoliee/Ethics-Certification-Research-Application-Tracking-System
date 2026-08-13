<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
use App\Enums\ResearchType;
use App\Enums\UserRole;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResLeadApplicationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_lead_landing_lists_only_formally_endorsed_workflow_records(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Visible Applicant',
            'institutional_identifier' => 'STU-VISIBLE-01',
        ]);
        $adviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Visible Adviser',
        ]);
        $endorsed = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'research_title' => 'Endorsed Community Ethics Study',
            'application_status' => ApplicationStatus::AdviserEndorsed,
            'current_stage' => ApplicationStage::ResScreening,
            'submitted_at' => now()->subDays(2),
            'status_updated_at' => now(),
        ]);
        Endorsement::create([
            'research_application_id' => $endorsed->id,
            'adviser_user_id' => $adviser->id,
            'endorsement_status' => EndorsementStatus::Endorsed,
            'endorsed_at' => now()->subHour(),
        ]);
        $underScreening = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'research_title' => 'Visible Screening Record',
            'application_status' => ApplicationStatus::UnderResScreening,
            'current_stage' => ApplicationStage::ResScreening,
            'submitted_at' => now()->subDays(3),
        ]);
        $beforeEndorsement = ResearchApplication::factory()->submittedToAdviser($adviser)->create([
            'applicant_user_id' => $applicant,
            'research_title' => 'Still With Adviser',
        ]);
        $draft = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'research_title' => 'Private Applicant Draft',
        ]);

        $this->actingAs($resLead)
            ->get(route('res.applications.index'))
            ->assertOk()
            ->assertSee('Applications Queue')
            ->assertSee($endorsed->research_title)
            ->assertSee($underScreening->research_title)
            ->assertDontSee($beforeEndorsement->research_title)
            ->assertDontSee($draft->research_title)
            ->assertSee('res-application-filter-actions', false)
            ->assertDontSee('res-filter-applicant-type', false)
            ->assertDontSee('res-filter-research-type', false)
            ->assertSee('res-filter-review-type', false)
            ->assertSee('res-filter-affiliation', false)
            ->assertSee('res-filter-date-range', false)
            ->assertSee('id="res-date-from"', false)
            ->assertSee('id="res-date-to"', false)
            ->assertSee('res-filter-apply', false)
            ->assertSee('res-application-panel', false)
            ->assertSee('res-application-scroll', false)
            ->assertDontSee('<th>Applicant Category</th>', false)
            ->assertDontSee('<th>Research Type</th>', false);

        $this->actingAs($resLead)
            ->get(route('res.applications.index', [
                'status' => ApplicationStatus::AdviserEndorsed->value,
                'q' => 'Community Ethics',
            ]))
            ->assertOk()
            ->assertSee($endorsed->research_title)
            ->assertDontSee($underScreening->research_title);

        ResearchApplication::factory()->count(14)->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'application_status' => ApplicationStatus::UnderResScreening,
            'current_stage' => ApplicationStage::ResScreening,
            'submitted_at' => now()->subWeek(),
        ]);

        $this->actingAs($resLead)
            ->get(route('res.applications.index'))
            ->assertOk()
            ->assertSee('aria-label="RES application queue pages"', false);
    }

    public function test_non_res_roles_cannot_open_the_res_endorsed_application_landing_page(): void
    {
        foreach ([UserRole::Applicant, UserRole::Adviser, UserRole::Reviewer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('res.applications.index'))
                ->assertRedirect(route('dashboard'));
        }
    }

    public function test_res_queue_searches_allowed_application_fields_but_never_applicant_identity(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $targetApplicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Solelyhidden Applicantname',
            'first_name' => 'Solelyhidden',
            'middle_name' => 'Privateidentity',
            'last_name' => 'Applicantname',
        ]);
        $targetAdviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Queue Needle Adviser',
        ]);
        $target = ResearchApplication::factory()->create([
            'application_code' => 'ECRATS-QUEUE-NEEDLE',
            'applicant_user_id' => $targetApplicant->id,
            'adviser_user_id' => $targetAdviser->id,
            'applicant_type' => 'student',
            'research_title' => 'Queue Needle Privacy Study',
            'research_type' => ResearchType::Capstone,
            'research_category' => 'Needle Ethics Category',
            'institution' => 'Needle Institute',
            'department' => 'Needle Department',
            'program' => 'Needle Research Program',
            'review_type' => 'expedited',
            'application_status' => ApplicationStatus::AwaitingReviewerAssignment,
            'current_stage' => ApplicationStage::ResScreening,
            'submitted_at' => '2026-07-10 08:00:00',
        ]);
        Endorsement::create([
            'research_application_id' => $target->id,
            'adviser_user_id' => $targetAdviser->id,
            'endorsement_status' => EndorsementStatus::Endorsed,
            'endorsed_at' => '2026-07-15 10:00:00',
        ]);

        $otherApplicant = User::factory()->create(['role' => UserRole::Applicant, 'name' => 'Other Applicant']);
        $otherAdviser = User::factory()->create(['role' => UserRole::Adviser, 'name' => 'Other Adviser']);
        $other = ResearchApplication::factory()->create([
            'application_code' => 'ECRATS-QUEUE-OTHER',
            'applicant_user_id' => $otherApplicant->id,
            'adviser_user_id' => $otherAdviser->id,
            'applicant_type' => 'faculty',
            'research_title' => 'Different Research Record',
            'research_type' => ResearchType::Thesis,
            'institution' => 'Other Institute',
            'program' => 'Other Program',
            'review_type' => 'full_board',
            'application_status' => ApplicationStatus::UnderFullBoardReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'submitted_at' => '2026-06-01 08:00:00',
        ]);
        Endorsement::create([
            'research_application_id' => $other->id,
            'adviser_user_id' => $otherAdviser->id,
            'endorsement_status' => EndorsementStatus::Endorsed,
            'endorsed_at' => '2026-06-05 10:00:00',
        ]);

        foreach ([
            $target->application_code,
            'Needle Privacy',
            'Needle Adviser',
            'Needle Ethics Category',
            'Needle Institute',
            'Needle Department',
            'Needle Research Program',
        ] as $search) {
            $this->actingAs($resLead)
                ->get(route('res.applications.index', ['q' => $search]))
                ->assertOk()
                ->assertSee($target->research_title)
                ->assertDontSee($other->research_title);
        }

        foreach (['Solelyhidden', 'Privateidentity', 'Applicantname'] as $applicantName) {
            $this->actingAs($resLead)
                ->get(route('res.applications.index', ['q' => $applicantName]))
                ->assertOk()
                ->assertDontSee($target->research_title);
        }

        $this->actingAs($resLead)
            ->get(route('res.applications.index', [
                'status' => ApplicationStatus::AwaitingReviewerAssignment->value,
                'review_type' => 'expedited',
                'affiliation' => 'Needle Research Program',
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertSee($target->research_title)
            ->assertDontSee($other->research_title);

        // Landing-page simplification never mutates the collected application attributes.
        $this->assertSame('student', $target->fresh()->applicant_type);
        $this->assertSame(ResearchType::Capstone, $target->fresh()->research_type);
    }

    public function test_res_application_filters_and_table_have_container_responsive_overflow_rules(): void
    {
        $css = file_get_contents(resource_path('css/dashboard.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.res-application-scroll\s*\{[^}]*width:\s*100%;[^}]*max-width:\s*100%;[^}]*min-width:\s*0;[^}]*overflow-x:\s*auto;[^}]*overflow-y:\s*hidden;/s',
            $css,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.res-application-scroll\s*\{[^}]*max-height:/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.res-application-table\s*\{[^}]*min-width:\s*940px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@container application-workspace \(max-width:\s*1120px\)\s*\{[^}]*\.application-filter-bar\.application-filter-bar-res\s*\{[^}]*grid-template-columns:\s*repeat\(2,/s',
            $css,
        );
        $this->assertStringContainsString('"search search status apply"', $css);
        $this->assertStringContainsString('"review affiliation date clear"', $css);
        $this->assertStringContainsString('"search search"', $css);
        $this->assertStringContainsString('"status review"', $css);
        $this->assertStringContainsString('"affiliation affiliation"', $css);
        $this->assertStringContainsString('"date date"', $css);
        $this->assertStringNotContainsString('res-filter-applicant-type', $css);
        $this->assertStringNotContainsString('res-filter-research-type', $css);
        $this->assertStringNotContainsString('res-filter-semester', $css);
        $this->assertStringNotContainsString('res-filter-academic-year', $css);
        $this->assertStringNotContainsString('res-filter-date-from', $css);
        $this->assertStringNotContainsString('res-filter-date-to', $css);
        $this->assertMatchesRegularExpression(
            '/@container application-workspace \(max-width:\s*560px\)/',
            $css,
        );
    }
}
