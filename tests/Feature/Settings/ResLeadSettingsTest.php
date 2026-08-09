<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\AcademicTerm;
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
            ->assertSee('Profile')
            ->assertSee('Deadline Configuration')
            ->assertSee('Security and Privacy')
            ->assertDontSee('settings-section-heading-centered', false)
            ->assertSee('Revision Period')
            ->assertSee('Reviewing of Revision Period')
            ->assertDontSee('Release of Decision &amp; Certificate', false)
            ->assertSee('Upcoming Deadline')
            ->assertSee('Active Date Range')
            ->assertDontSee('Manual Toggles On')
            ->assertSee('settings-deadline-table', false)
            ->assertSee('data-deadline-process', false)
            ->assertSee('data-deadline-toggle-label', false)
            ->assertSee('>Off<', false)
            ->assertDontSee('settings-process-row', false)
            ->assertSee('data-settings-confirm-dialog', false)
            ->assertDontSee('result-release');
        $this->assertSame('Asia/Manila', config('app.timezone'));

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

    public function test_deadline_configuration_responds_to_the_sidebar_adjusted_workspace_width(): void
    {
        $css = (string) file_get_contents(resource_path('css/dashboard.css'));

        $this->assertMatchesRegularExpression(
            '/\.res-settings-page\s*\{[^}]*container-name:\s*res-settings-workspace;[^}]*container-type:\s*inline-size;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.settings-deadline-overview\s*\{[^}]*width:\s*100%;[^}]*min-width:\s*0;[^}]*grid-template-columns:\s*minmax\(560px,/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@container res-settings-workspace \(max-width:\s*1080px\)\s*\{\s*\.settings-deadline-overview\s*\{[^}]*grid-template-columns:\s*1fr;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.settings-table-field input\s*\{[^}]*width:\s*100%;[^}]*min-width:\s*0;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.settings-deadline-table-wrap\s*\{[^}]*width:\s*calc\(100% - 40px\);/s',
            $css,
        );
    }

    public function test_res_lead_can_save_complete_semester_deadlines_and_timeline_events(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->put(route('res.settings.deadlines.update'), $this->deadlinePayload())
            ->assertRedirect()
            ->assertSessionHas('status');

        $term = AcademicTerm::query()
            ->where('semester', '1st Semester')
            ->where('academic_year', '2026-2027')
            ->firstOrFail();

        foreach (DeadlineProcessCatalog::definitions() as $key => $definition) {
            $configuration = DeadlineConfiguration::query()
                ->where('academic_term_id', $term->id)
                ->where('deadline_key', 'like', "%{$key}")
                ->firstOrFail();
            $this->assertSame('1st Semester, A.Y. 2026-2027', $configuration->semester_label);
            $this->assertNull($configuration->manual_status);
            $this->assertSame($definition['audience_role'], $configuration->audience_role);
            $this->assertSame(100, $configuration->priority);

            $event = TimelineCalendarEvent::query()
                ->where('academic_term_id', $term->id)
                ->where('milestone_key', 'like', "%{$definition['timeline_key']}")
                ->firstOrFail();
            $this->assertSame('1st Semester, A.Y. 2026-2027', $event->term_label);
            $this->assertSame($definition['timeline_label'], $event->label);
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.deadlines_updated',
            'academic_term_id' => $term->id,
        ]);
    }

    public function test_deadline_configuration_accepts_expired_ranges_and_leaves_them_automatic(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $processKey = DeadlineProcessCatalog::keys()[0];

        foreach (['1', '0'] as $manualOpen) {
            $payload = $this->deadlinePayload();
            $payload['processes'][$processKey] = [
                'starts_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
                'due_at' => now()->subHour()->format('Y-m-d\TH:i'),
                'is_open' => $manualOpen,
                'override_changed' => '0',
            ];

            $this->actingAs($resLead)
                ->from(route('res.settings.index'))
                ->put(route('res.settings.deadlines.update'), $payload)
                ->assertRedirect()
                ->assertSessionDoesntHaveErrors();
        }

        $this->assertNull(DeadlineConfiguration::query()
            ->where('deadline_key', 'like', "%{$processKey}")
            ->firstOrFail()
            ->manual_status);
    }

    public function test_deadline_configuration_accepts_a_past_term_start_date(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $payload = $this->deadlinePayload();
        $payload['term_starts_on'] = now()->subDay()->toDateString();

        $this->actingAs($resLead)
            ->from(route('res.settings.index'))
            ->put(route('res.settings.deadlines.update'), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, AcademicTerm::query()->count());
    }

    public function test_settings_term_label_uses_only_the_current_configured_timeframe(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $term = AcademicTerm::create([
            'semester' => 'Current Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->actingAs($resLead)
            ->get(route('res.settings.index'))
            ->assertOk()
            ->assertSee('Current Semester, A.Y. 2026-2027');

        $term->update(['ends_at' => now()->subMinute()]);

        $this->actingAs($resLead)
            ->get(route('res.settings.index'))
            ->assertOk()
            ->assertSee('<dt>Active Term</dt><dd>Semester and Academic Year</dd>', false)
            ->assertDontSee('Current Semester, A.Y. 2026-2027');
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

    public function test_password_mismatch_reports_both_new_password_fields(): void
    {
        $resLead = User::factory()->create([
            'role' => UserRole::ResLead,
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($resLead)
            ->patch(route('res.settings.password.update'), [
                'current_password' => 'current-password',
                'password' => 'first-secure-password',
                'password_confirmation' => 'different-secure-password',
            ])
            ->assertSessionHasErrors(['password', 'password_confirmation']);

        $this->assertTrue(Hash::check('current-password', $resLead->fresh()->password));
    }

    public function test_automatic_deadlines_store_no_manual_override(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $payload = $this->deadlinePayload();

        foreach (DeadlineProcessCatalog::keys() as $key) {
            $payload['processes'][$key]['is_open'] = '0';
        }

        $this->actingAs($resLead)
            ->put(route('res.settings.deadlines.update'), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(DeadlineProcessCatalog::keys(), DeadlineConfiguration::query()
            ->orderBy('id')
            ->get()
            ->map(fn (DeadlineConfiguration $deadline): string => DeadlineProcessCatalog::keyForDeadlineKey($deadline->deadline_key))
            ->all());
        $this->assertSame(0, DeadlineConfiguration::query()->whereNotNull('manual_status')->count());
    }

    /** @return array<string, mixed> */
    private function deadlinePayload(): array
    {
        $processes = [];
        $startsAt = now()->addDay()->startOfHour();
        $dueAt = $startsAt->copy()->addWeeks(2);

        foreach (DeadlineProcessCatalog::keys() as $key) {
            $processes[$key] = [
                'starts_at' => $startsAt->format('Y-m-d\TH:i'),
                'due_at' => $dueAt->format('Y-m-d\TH:i'),
                'is_open' => '1',
                'override_changed' => '0',
            ];
        }

        return [
            'semester' => '1st Semester',
            'academic_year' => '2026-2027',
            'term_starts_on' => now()->toDateString(),
            'term_ends_on' => now()->addMonths(5)->toDateString(),
            'processes' => $processes,
        ];
    }
}
