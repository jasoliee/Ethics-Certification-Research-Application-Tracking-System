<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
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

    public function test_temporary_pages_have_clickable_home_breadcrumb_and_non_clickable_current_crumb(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);

        $this->actingAs($applicant)
            ->get(route('applicant.settings.index'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('<span aria-current="page">Settings</span>', false)
            ->assertSeeInOrder(['dashboard-topbar', 'dashboard-breadcrumbs', 'dashboard-content'], false)
            ->assertSee('Account settings will be managed here.');
    }

    public function test_non_sidebar_dashboard_actions_and_notification_pages_also_resolve(): void
    {
        $cases = [
            UserRole::Applicant->value => [
                'dashboard',
                'applicant.applications.create',
                'applicant.notifications.index',
                'applicant.profile.show',
            ],
            UserRole::Adviser->value => ['dashboard', 'adviser.notifications.index', 'adviser.profile.show'],
            UserRole::Reviewer->value => ['dashboard', 'reviewer.notifications.index', 'reviewer.profile.show'],
            UserRole::ResLead->value => ['dashboard', 'res.notifications.index', 'res.profile.show'],
        ];

        foreach ($cases as $role => $routes) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($routes as $routeName) {
                $this->actingAs($user)->get(route($routeName))->assertOk();
            }
        }
    }

    public function test_each_sidebar_excludes_navigation_owned_by_other_roles(): void
    {
        $cases = [
            UserRole::Applicant->value => ['User Management', 'Review Monitoring', 'Assignments', 'Reviewer', 'Certificates', 'Notifications'],
            UserRole::Adviser->value => ['Certificates', 'Review Monitoring', 'Assignments', 'Notifications'],
            UserRole::Reviewer->value => ['Applicants', 'User Management', 'Certificates', 'Notifications'],
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

    public function test_shared_shell_has_clickable_kld_logo_footer_and_role_profile_link(): void
    {
        $faculty = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => 'faculty',
        ]);

        $this->actingAs($faculty)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="https://kld.edu.ph/profile.php"', false)
            ->assertSee('Faculty Researcher')
            ->assertSee('href="'.route('applicant.profile.show').'"', false)
            ->assertSee('class="dashboard-footer"', false)
            ->assertSee('About KLD')
            ->assertSee('Helpful Links');
    }

    public function test_role_middleware_blocks_direct_access_to_every_other_role_area(): void
    {
        $routes = [
            UserRole::Applicant->value => 'applicant.settings.index',
            UserRole::Adviser->value => 'adviser.settings.index',
            UserRole::Reviewer->value => 'reviewer.settings.index',
            UserRole::ResLead->value => 'res.settings.index',
        ];

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($routes as $allowedRole => $routeName) {
                if ($allowedRole === $role->value) {
                    continue;
                }

                $this->actingAs($user)
                    ->get(route($routeName))
                    ->assertRedirect(route(RoleHome::routeNameFor($role)));
            }
        }
    }

    public function test_record_policies_prevent_applications_and_assignments_from_leaking_between_users(): void
    {
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
        ]);
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
        ]);

        $this->actingAs($owner)->get(route('applicant.applications.show', $application))->assertOk();
        $this->actingAs($owner)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertSee('href="'.route('applicant.applications.show', $application).'"', false)
            ->assertSee('<span aria-current="page">Submitted Requirements</span>', false);
        $this->actingAs($otherApplicant)->get(route('applicant.applications.show', $application))->assertForbidden();
        $this->actingAs($otherApplicant)->get(route('applicant.applications.requirements', $application))->assertForbidden();
        $this->actingAs($adviser)->get(route('adviser.applications.show', $application))->assertOk();
        $this->actingAs($otherAdviser)->get(route('adviser.applications.show', $application))->assertForbidden();
        $this->actingAs($reviewer)->get(route('reviewer.assignments.show', $assignment))->assertOk();
        $this->actingAs($otherReviewer)->get(route('reviewer.assignments.show', $assignment))->assertForbidden();
        $this->actingAs($resLead)->get(route('res.applications.show', $application))->assertOk();
    }
}
