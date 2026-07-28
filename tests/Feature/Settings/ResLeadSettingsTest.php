<?php

namespace Tests\Feature\Settings;

use App\Enums\DeadlineManualStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\DeadlineConfiguration;
use App\Models\TimelineCalendarEvent;
use App\Models\User;
use App\Support\DeadlineProcessCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResLeadSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_and_write_routes_are_restricted_to_res_lead(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->get(route('res.settings.index'))
            ->assertOk()
            ->assertSee('Deadline Configuration')
            ->assertSee('Personal Account Management');

        foreach ([UserRole::Applicant, UserRole::Adviser, UserRole::Reviewer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('res.settings.index'))
                ->assertRedirect(route('dashboard'));

            $this->actingAs($user)
                ->put(route('res.settings.deadlines.update'), $this->deadlinePayload())
                ->assertRedirect(route('dashboard'));
        }
    }

    public function test_res_lead_can_save_complete_semester_deadlines_and_timeline_events(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->put(route('res.settings.deadlines.update'), $this->deadlinePayload())
            ->assertRedirect()
            ->assertSessionHas('status');

        foreach (DeadlineProcessCatalog::definitions() as $key => $definition) {
            $configuration = DeadlineConfiguration::where('deadline_key', $key)->firstOrFail();
            $this->assertSame('1st Semester, A.Y. 2026-2027', $configuration->semester_label);
            $this->assertSame(DeadlineManualStatus::Open, $configuration->manual_status);
            $this->assertSame($definition['audience_role'], $configuration->audience_role);
            $this->assertSame(100, $configuration->priority);

            $event = TimelineCalendarEvent::where('milestone_key', $definition['timeline_key'])->firstOrFail();
            $this->assertSame('1st Semester, A.Y. 2026-2027', $event->term_label);
            $this->assertSame($definition['timeline_label'], $event->label);
        }

        $this->assertSame(1, AuditLog::where('action', 'settings.deadlines_updated')->count());
    }

    public function test_res_lead_can_update_own_username_and_password_with_current_password(): void
    {
        $resLead = User::factory()->create([
            'role' => UserRole::ResLead,
            'username' => 'res.old',
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($resLead)
            ->patch(route('res.settings.username.update'), ['username' => 'RES.NEW'])
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame('res.new', $resLead->refresh()->username);

        $this->actingAs($resLead)
            ->patch(route('res.settings.password.update'), [
                'current_password' => 'current-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $resLead->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $resLead->password));
        $this->assertNotNull($resLead->password_changed_at);
        $this->assertSame(1, AuditLog::where('action', 'settings.username_updated')->count());
        $this->assertSame(1, AuditLog::where('action', 'settings.password_updated')->count());
    }

    public function test_res_lead_password_change_rejects_an_incorrect_current_password(): void
    {
        $resLead = User::factory()->create([
            'role' => UserRole::ResLead,
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($resLead)
            ->from(route('res.settings.index'))
            ->patch(route('res.settings.password.update'), [
                'current_password' => 'incorrect-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect(route('res.settings.index'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('current-password', $resLead->fresh()->password));
    }

    /** @return array<string, mixed> */
    private function deadlinePayload(): array
    {
        $processes = [];

        foreach (DeadlineProcessCatalog::keys() as $key) {
            $processes[$key] = [
                'starts_at' => '2026-08-01T08:00',
                'due_at' => '2026-08-15T17:00',
                'is_open' => '1',
            ];
        }

        return [
            'semester_label' => '1st Semester, A.Y. 2026-2027',
            'processes' => $processes,
        ];
    }
}
