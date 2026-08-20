<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReviewerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_settings_page_is_functional_and_owner_scoped(): void
    {
        $reviewer = User::factory()->reviewer(['Technical'])->create();

        $this->actingAs($reviewer)
            ->get(route('reviewer.settings.index'))
            ->assertOk()
            ->assertSee('Security and Privacy')
            ->assertDontSee('Reviewer Classification')
            ->assertDontSee('Technical')
            ->assertSee(route('reviewer.settings.username.update'), false)
            ->assertSee(route('reviewer.settings.password.update'), false)
            ->assertDontSee('Account settings will be managed here.');

        foreach ([UserRole::Applicant, UserRole::Adviser, UserRole::ResLead] as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('reviewer.settings.index'));

            $response->status() === 403
                ? $response->assertForbidden()
                : $response->assertRedirect(route('dashboard'));
        }
    }

    public function test_reviewer_can_update_own_username_and_password(): void
    {
        $reviewer = User::factory()->create([
            'role' => UserRole::Reviewer,
            'username' => 'review.old',
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($reviewer)
            ->patch(route('reviewer.settings.username.update'), ['username' => 'REVIEW.NEW'])
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame('review.new', $reviewer->refresh()->username);

        $this->actingAs($reviewer)
            ->patch(route('reviewer.settings.password.update'), [
                'current_password' => 'current-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $reviewer->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $reviewer->password));
        $this->assertSame(1, AuditLog::where('action', 'settings.username_updated')->count());
        $this->assertSame(1, AuditLog::where('action', 'settings.password_updated')->count());
    }
}
