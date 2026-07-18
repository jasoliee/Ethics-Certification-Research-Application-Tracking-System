<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_dropdown_uses_database_notifications_and_mark_all_read_updates_them(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $applicant->notify(new DashboardUpdateNotification([
            'title' => 'Requirement status updated',
            'message' => 'Payment Proof is still pending.',
            'icon' => 'clipboard',
            'tone' => 'orange',
            'route' => 'applicant.applications.index',
        ]));

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Requirement status updated')
            ->assertSee('Payment Proof is still pending.')
            ->assertSee('1 unread notifications');

        $this->assertSame(1, $applicant->unreadNotifications()->count());

        $this->actingAs($applicant)
            ->post(route('notifications.mark-all-read'))
            ->assertRedirect();

        $this->assertSame(0, $applicant->fresh()->unreadNotifications()->count());
    }

    public function test_notification_and_profile_controls_have_accessible_menu_contracts_and_secure_logout_form(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);

        $this->actingAs($reviewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-controls="dashboard-notification-menu"', false)
            ->assertSee('aria-controls="dashboard-profile-menu"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('href="'.route('reviewer.profile.show').'"', false)
            ->assertSee('href="'.route('reviewer.settings.index').'"', false);
    }

    public function test_notification_with_missing_route_parameters_falls_back_to_the_role_notification_page(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $applicant->notify(new DashboardUpdateNotification([
            'title' => 'Application updated',
            'message' => 'Open the application for details.',
            'route' => 'applicant.applications.show',
        ]));

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('applicant.notifications.index').'"', false);
    }
}
