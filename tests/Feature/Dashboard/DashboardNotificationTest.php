<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            ->assertSee('href="'.route('adviser.profile.show').'"', false)
            ->assertSee('href="'.route('adviser.settings.index').'"', false);
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

    public function test_notification_inbox_actions_are_owner_scoped_and_deleted_items_can_be_restored(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $other = User::factory()->create(['role' => UserRole::Applicant]);
        $owner->notify(new DashboardUpdateNotification([
            'title' => 'Owned notification',
            'message' => 'Only the owner may change this record.',
        ]));
        $other->notify(new DashboardUpdateNotification([
            'title' => 'Other notification',
            'message' => 'This belongs to another user.',
        ]));
        $owned = $owner->notifications()->firstOrFail();
        $foreign = $other->notifications()->firstOrFail();

        $this->actingAs($owner)
            ->get(route('applicant.notifications.index', ['read_status' => 'unread']))
            ->assertOk()
            ->assertSee('Owned notification')
            ->assertSee('Mark Read')
            ->assertSee('Mark Unread')
            ->assertSee('Delete All')
            ->assertSee('Action');

        $this->actingAs($owner)
            ->patch(route('notifications.read-status', $foreign), ['action' => 'mark_read'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('notifications.destroy', $owned))
            ->assertRedirect();
        $this->assertSoftDeleted('notifications', ['id' => $owned->id]);

        $this->actingAs($owner)
            ->get(route('notifications.bin'))
            ->assertOk()
            ->assertSee('Owned notification')
            ->assertSee('Restore All');

        $this->actingAs($owner)
            ->patch(route('notifications.bin.restore', $owned->id))
            ->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $owned->id, 'deleted_at' => null]);
    }

    public function test_notification_bin_purges_records_after_seven_days_and_supports_confirmed_permanent_delete(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $owner->notify(new DashboardUpdateNotification(['title' => 'Expired', 'message' => 'Old bin item.']));
        $expired = $owner->notifications()->firstOrFail();
        $expired->delete();
        $expired->forceFill(['deleted_at' => Carbon::now()->subDays(8)])->save();

        $owner->notify(new DashboardUpdateNotification(['title' => 'Recent', 'message' => 'Recent bin item.']));
        $recent = $owner->notifications()->firstOrFail();
        $recent->delete();

        $this->actingAs($owner)
            ->get(route('notifications.bin'))
            ->assertOk()
            ->assertDontSee('Expired')
            ->assertSee('Recent');
        $this->assertDatabaseMissing('notifications', ['id' => $expired->id]);

        $this->actingAs($owner)
            ->delete(route('notifications.bin.destroy', $recent->id))
            ->assertRedirect();
        $this->assertDatabaseMissing('notifications', ['id' => $recent->id]);
    }
}
