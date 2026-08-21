<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            ->assertSee('Action')
            ->assertSee('data-notification-confirm-dialog', false)
            ->assertSee('data-notification-confirm-mode="inbox-selected"', false)
            ->assertDontSee('onsubmit="return confirm(', false);

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
            ->assertSee('Restore All')
            ->assertSee('data-notification-confirm-mode="bin-selected"', false)
            ->assertDontSee('window.confirm(', false);

        $this->actingAs($owner)
            ->patch(route('notifications.bin.restore', $owned->id))
            ->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $owned->id, 'deleted_at' => null]);
    }

    public function test_notification_type_filter_query_discards_inherited_created_at_ordering(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $owner->notify(new DashboardUpdateNotification([
            'title' => 'MySQL distinct regression',
            'message' => 'The type selector must not retain the notification relation ordering.',
        ]));
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($owner)
            ->get(route('applicant.notifications.index'))
            ->assertOk()
            ->assertSee('MySQL distinct regression');

        $typeQuery = collect(DB::getQueryLog())->first(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'select distinct')
                && str_contains(strtolower($query['query']), 'type')
                && str_contains(strtolower($query['query']), 'notifications'),
        );

        $this->assertNotNull($typeQuery, 'The notification type DISTINCT query was not executed.');
        $this->assertStringContainsString('order by', strtolower($typeQuery['query']));
        $orderClause = strtolower(substr($typeQuery['query'], strripos(strtolower($typeQuery['query']), 'order by')));
        $this->assertStringContainsString('type', $orderClause);
        $this->assertStringNotContainsString('created_at', $orderClause);
    }

    public function test_notification_filters_and_pagination_are_owner_scoped_at_twenty_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $other = User::factory()->create(['role' => UserRole::Applicant]);
        foreach (range(1, 25) as $index) {
            $owner->notify(new DashboardUpdateNotification([
                'title' => "Owned notification {$index}",
                'message' => 'Paginated owner-scoped notification.',
            ]));
            $notification = $owner->notifications()->get()->first(
                fn ($item): bool => ($item->data['title'] ?? null) === "Owned notification {$index}",
            );
            $this->assertNotNull($notification);
            $notification->forceFill([
                'created_at' => Carbon::parse('2026-08-01')->addDays($index),
                'read_at' => $index % 2 === 0 ? now() : null,
            ])->save();
        }
        $other->notify(new DashboardUpdateNotification([
            'title' => 'Foreign notification must not leak',
            'message' => 'Other owner.',
        ]));

        $firstPage = $this->actingAs($owner)->get(route('applicant.notifications.index'));
        $firstPage->assertOk()
            ->assertSee('Notification pages')
            ->assertDontSee('Foreign notification must not leak');
        $this->assertSame(20, $firstPage->viewData('notifications')->count());
        $this->assertSame(25, $firstPage->viewData('notifications')->total());

        $secondPage = $this->actingAs($owner)->get(route('applicant.notifications.index', ['page' => 2]));
        $secondPage->assertOk();
        $this->assertSame(5, $secondPage->viewData('notifications')->count());

        $filtered = $this->actingAs($owner)->get(route('applicant.notifications.index', [
            'date' => '2026-08-11',
            'type' => DashboardUpdateNotification::class,
            'read_status' => 'read',
        ]));
        $filtered->assertOk()->assertSee('Owned notification 10')->assertDontSee('Owned notification 11');
        $this->assertSame(1, $filtered->viewData('notifications')->total());
    }

    public function test_notification_selected_and_all_actions_cover_inbox_and_bin_without_cross_owner_mutation(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $other = User::factory()->create(['role' => UserRole::Applicant]);
        foreach (['First selected', 'Second selected', 'Third selected'] as $title) {
            $owner->notify(new DashboardUpdateNotification(['title' => $title, 'message' => 'Owner action.']));
        }
        $other->notify(new DashboardUpdateNotification(['title' => 'Foreign selected', 'message' => 'Other owner.']));
        $owned = $owner->notifications()->orderBy('id')->get();
        $foreign = $other->notifications()->firstOrFail();

        $this->actingAs($owner)->post(route('notifications.bulk'), [
            'notification_ids' => [$owned[0]->id, $foreign->id],
            'action' => 'mark_read',
        ])->assertRedirect();
        $this->assertNotNull($owned[0]->refresh()->read_at);
        $this->assertNull($foreign->refresh()->read_at);

        $this->actingAs($owner)->post(route('notifications.bulk'), [
            'notification_ids' => [$owned[0]->id, $owned[1]->id],
            'action' => 'delete',
        ])->assertRedirect();
        $this->assertSame(2, $owner->notifications()->onlyTrashed()->count());

        $this->actingAs($owner)->post(route('notifications.bin.bulk'), [
            'notification_ids' => [$owned[0]->id],
            'action' => 'restore',
        ])->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $owned[0]->id, 'deleted_at' => null]);

        $this->actingAs($owner)->post(route('notifications.all'), ['action' => 'delete'])->assertRedirect();
        $this->assertSame(3, $owner->notifications()->onlyTrashed()->count());
        $this->assertSame(0, $owner->notifications()->count());

        $this->actingAs($owner)->post(route('notifications.bin.all'), ['action' => 'restore'])->assertRedirect();
        $this->assertSame(3, $owner->notifications()->count());
        $this->actingAs($owner)->post(route('notifications.all'), ['action' => 'delete'])->assertRedirect();
        $this->actingAs($owner)->post(route('notifications.bin.all'), ['action' => 'force_delete'])->assertRedirect();
        $this->assertSame(0, $owner->notifications()->withTrashed()->count());
        $this->assertSame(1, $other->notifications()->count());
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

    public function test_database_failures_render_a_safe_message_without_sql_or_stack_details(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        Schema::drop('notifications');

        $this->actingAs($owner)
            ->get(route('applicant.notifications.index'))
            ->assertStatus(500)
            ->assertSee('Data temporarily unavailable')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('QueryException')
            ->assertDontSee('vendor/laravel');
    }
}
