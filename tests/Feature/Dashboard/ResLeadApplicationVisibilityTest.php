<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
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
            ->assertSee('Endorsed Applications')
            ->assertSee($endorsed->research_title)
            ->assertSee($underScreening->research_title)
            ->assertDontSee($beforeEndorsement->research_title)
            ->assertDontSee($draft->research_title)
            ->assertSee('res-application-filter-actions', false)
            ->assertSee('res-filter-academic-year', false)
            ->assertSee('res-filter-apply', false)
            ->assertSee('res-application-panel', false)
            ->assertSee('res-application-scroll', false);

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
            ->assertSee('aria-label="RES endorsed application pages"', false);
    }

    public function test_non_res_roles_cannot_open_the_res_endorsed_application_landing_page(): void
    {
        foreach ([UserRole::Applicant, UserRole::Adviser, UserRole::Reviewer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('res.applications.index'))
                ->assertRedirect(route('dashboard'));
        }
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
            '/\.res-application-table\s*\{[^}]*width:\s*100%;[^}]*min-width:\s*1180px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@container application-workspace \(max-width:\s*1280px\)\s*\{[^}]*\.application-filter-bar\.application-filter-bar-res\s*\{[^}]*grid-template-columns:\s*repeat\(4,/s',
            $css,
        );
        $this->assertStringContainsString('"search status semester apply"', $css);
        $this->assertStringContainsString('"from to year clear"', $css);
        $this->assertStringContainsString('"status semester"', $css);
        $this->assertStringContainsString('"from to"', $css);
        $this->assertMatchesRegularExpression(
            '/@container application-workspace \(max-width:\s*520px\)/',
            $css,
        );
    }
}
