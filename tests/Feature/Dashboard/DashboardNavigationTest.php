<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\DeadlineConfiguration;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Support\DashboardNavigation;
use App\Support\RoleHome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_navigation_item_opens_a_named_page_with_shared_layout_and_active_state(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach (DashboardNavigation::for($role) as $item) {
                $this->actingAs($user)
                    ->get(route($item['route']))
                    ->assertOk()
                    ->assertSee('dashboard-sidebar', false)
                    ->assertSee('dashboard-topbar', false)
                    ->assertSee('aria-current="page"', false);
            }
        }
    }

    public function test_every_role_receives_the_persistent_desktop_sidebar_toggle(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $homeRoute = RoleHome::routeNameFor($role);

            $this->actingAs($user)
                ->get(route($homeRoute))
                ->assertOk()
                ->assertSee('data-sidebar-toggle', false)
                ->assertSee('aria-controls="dashboard-sidebar"', false)
                ->assertSee('Hide navigation sidebar');
        }

        $layout = (string) file_get_contents(resource_path('views/layouts/dashboard.blade.php'));
        $javascript = (string) file_get_contents(resource_path('js/dashboard.js'));
        $stylesheet = (string) file_get_contents(resource_path('css/dashboard.css'));

        $this->assertStringContainsString('ecrats:dashboard-sidebar-collapsed', $layout);
        $this->assertStringContainsString('ecrats:dashboard-sidebar-collapsed', $javascript);
        $this->assertStringContainsString("top: 50vh;", $stylesheet);
        $this->assertStringContainsString("html[data-dashboard-sidebar-collapsed='true']", $stylesheet);
    }

    public function test_temporary_pages_have_clickable_home_breadcrumb_and_non_clickable_current_crumb(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);

        $this->actingAs($applicant)
            ->get(route('applicant.settings.index'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('<span aria-current="page">Settings</span>', false)
            ->assertSeeInOrder(['dashboard-topbar', 'dashboard-breadcrumbs', 'dashboard-content'], false)
            ->assertSee('data-inline-profile-form', false);
    }

    public function test_non_sidebar_dashboard_actions_and_notification_pages_also_resolve(): void
    {
        DeadlineConfiguration::create([
            'deadline_key' => 'navigation-application-submission',
            'title' => 'Application submission deadline',
            'audience_role' => UserRole::Applicant,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDay(),
            'priority' => 100,
            'is_active' => true,
        ]);

        $cases = [
            UserRole::Applicant->value => [
                'dashboard',
                'applicant.applications.create',
                'applicant.notifications.index',
                'applicant.profile.show',
            ],
            UserRole::Adviser->value => ['dashboard', 'adviser.notifications.index', 'adviser.profile.show'],
            UserRole::Reviewer->value => [
                'dashboard',
                'reviewer.reviews.index',
                'reviewer.notifications.index',
                'reviewer.profile.show',
                'reviewer.settings.index',
            ],
            UserRole::ResLead->value => ['dashboard', 'res.notifications.index', 'res.profile.show'],
        ];

        foreach ($cases as $role => $routes) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($routes as $routeName) {
                $this->actingAs($user)->get(route($routeName))->assertOk();
            }
        }
    }

    public function test_reviewer_navigation_is_a_supplementary_adviser_submenu(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->assertSame(
            ['Home', 'Application', 'Applicants', 'Reviewer'],
            array_column(DashboardNavigation::for($reviewer), 'label'),
        );

        $response = $this->actingAs($reviewer)
            ->get(route('dashboard'))
            ->assertOk();

        preg_match('/<nav class="dashboard-sidebar-nav".*?<\/nav>/s', $response->getContent(), $sidebar);
        $this->assertNotEmpty($sidebar);
        $this->assertStringContainsString('>Home</span>', $sidebar[0]);
        $this->assertStringContainsString('>Reviewer</span>', $sidebar[0]);
        $this->assertStringContainsString('>Reviewer Dashboard</span>', $sidebar[0]);
        $this->assertStringContainsString('>Assignments</span>', $sidebar[0]);
        $this->assertStringNotContainsString('>Notifications</span>', $sidebar[0]);
        $this->assertStringNotContainsString('>Settings</span>', $sidebar[0]);

        foreach (['reviewer.dashboard', 'reviewer.reviews.index', 'reviewer.notifications.index', 'reviewer.settings.index'] as $routeName) {
            $this->actingAs($reviewer)->get(route($routeName))->assertOk();
        }
    }

    public function test_each_sidebar_excludes_navigation_owned_by_other_roles(): void
    {
        $cases = [
            UserRole::Applicant->value => ['User Management', 'Review Monitoring', 'Assignments', 'Reviewer', 'Certificates', 'Notifications'],
            UserRole::Adviser->value => ['Certificates', 'Review Monitoring', 'Assignments', 'Reviewer Dashboard', 'Notifications'],
            UserRole::ResLead->value => ['Applicants', 'Assignments', 'Reviewer', 'Notifications'],
        ];

        foreach ($cases as $role => $excludedLabels) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route(RoleHome::routeNameFor($role)))->assertOk();

            foreach ($excludedLabels as $label) {
                $response->assertDontSee('>'.$label.'</span>', false);
            }
        }
    }

    public function test_applicant_navigation_combines_revision_and_certificate_work(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Revision and Certificates')
            ->assertSee('href="'.route('applicant.revision-certificates.index').'"', false)
            ->assertDontSee('>Reviewer</span>', false)
            ->assertDontSee('>Certificates</span>', false);

        $this->actingAs($applicant)
            ->get(route('applicant.revision-certificates.index'))
            ->assertOk()
            ->assertSee('<span aria-current="page">Revision and Certificates</span>', false);
    }

    public function test_reports_and_audit_are_res_owned_and_applicant_report_urls_are_unavailable(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->assertNotContains('Reports', array_column(DashboardNavigation::for(UserRole::Applicant), 'label'));
        $this->assertContains('Reports', array_column(DashboardNavigation::for(UserRole::ResLead), 'label'));

        $this->actingAs($applicant)->get('/applicant/reports')->assertNotFound();
        $this->actingAs($applicant)->get('/reports')->assertNotFound();
        $this->actingAs($applicant)
            ->get(route('res.reports.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($resLead)
            ->get(route('res.reports.index'))
            ->assertOk()
            ->assertSee('Audit Log')
            ->assertSee(route('res.reports.audit.index'), false);
        $this->actingAs($resLead)
            ->get(route('res.reports.audit.index'))
            ->assertOk()
            ->assertSeeInOrder(['Home', 'Reports', 'Audit Log']);
    }

    public function test_shared_shell_has_expected_navigation_footer_and_profile_links(): void
    {
        $faculty = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => 'faculty',
        ]);

        $this->assertNotContains(
            'Settings',
            array_column(DashboardNavigation::for(UserRole::Applicant), 'label'),
        );

        $this->actingAs($faculty)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="https://kld.edu.ph/profile.php"', false)
            ->assertSee('Faculty Researcher')
            ->assertDontSee('href="'.route('applicant.profile.show').'"', false)
            ->assertSee('href="'.route('applicant.settings.index').'"', false)
            ->assertDontSee('<nav class="dashboard-breadcrumbs"', false)
            ->assertSee('class="dashboard-footer"', false)
            ->assertSee('About ECRATS')
            ->assertSee('https://www.google.com/maps/search/?api=1&query=Kolehiyo+ng+Lungsod+ng+Dasmarinas', false)
            ->assertDontSee('KLD Login')
            ->assertSee('Helpful Links');
    }

    public function test_role_middleware_blocks_direct_access_to_every_other_role_area(): void
    {
        $routes = [
            UserRole::Applicant->value => 'applicant.settings.index',
            UserRole::Adviser->value => 'adviser.settings.index',
            UserRole::ResLead->value => 'res.settings.index',
        ];

        foreach ([UserRole::Applicant, UserRole::Adviser, UserRole::ResLead] as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($routes as $allowedRole => $routeName) {
                if ($allowedRole === $role->value) {
                    continue;
                }

                $this->actingAs($user)
                    ->get(route($routeName))
                    ->assertRedirect(route(RoleHome::routeNameFor($role)));
            }

            $this->actingAs($user)->get(route('reviewer.settings.index'))->assertForbidden();
        }

        $reviewerAdviser = User::factory()->reviewer()->create();
        $this->actingAs($reviewerAdviser)->get(route('reviewer.settings.index'))->assertOk();
    }

    public function test_record_policies_prevent_applications_and_assignments_from_leaking_between_users(): void
    {
        // Arrange each role, one formally submitted application, and one assigned reviewer record.
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $otherApplicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $otherAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $otherReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $owner->id,
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::UnderExpeditedReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'submitted_at' => now(),
        ]);
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
        ]);

        // Act across every record route and assert only owners, assignees, and the REU Lead receive access.
        $this->actingAs($owner)->get(route('applicant.applications.show', $application))->assertOk();
        $this->actingAs($owner)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertSee('href="'.route('applicant.applications.show', $application).'"', false)
            ->assertSee('<span aria-current="page">Document Submission</span>', false);
        $this->actingAs($otherApplicant)->get(route('applicant.applications.show', $application))->assertForbidden();
        $this->actingAs($otherApplicant)->get(route('applicant.applications.requirements', $application))->assertForbidden();
        $this->actingAs($adviser)->get(route('adviser.applications.show', $application))->assertOk();
        $this->actingAs($otherAdviser)->get(route('adviser.applications.show', $application))->assertForbidden();
        $this->actingAs($reviewer)->get(route('reviewer.assignments.show', $assignment))->assertOk();
        $this->actingAs($otherReviewer)->get(route('reviewer.assignments.show', $assignment))->assertForbidden();
        $this->actingAs($resLead)->get(route('res.applications.show', $application))->assertOk();
    }
}
