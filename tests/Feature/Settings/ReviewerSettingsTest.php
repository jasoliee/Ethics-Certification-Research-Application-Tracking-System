<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_reviewer_can_store_and_privately_preview_a_verified_worksheet_signature(): void
    {
        Storage::fake('local');
        $reviewer = User::factory()->reviewer(['Technical'])->create();

        $this->actingAs($reviewer)
            ->put(route('reviewer.settings.worksheet-signatory.update'), [
                'worksheet_signatory_name' => '  Dr. Reviewer Signature  ',
                'signature' => UploadedFile::fake()->image('signature.png', 400, 100),
            ])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $reviewer->refresh();
        $this->assertSame('Dr. Reviewer Signature', $reviewer->worksheet_signatory_name);
        $this->assertSame(400, $reviewer->worksheet_signature_width);
        $this->assertSame(100, $reviewer->worksheet_signature_height);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', (string) $reviewer->worksheet_signature_sha256);
        Storage::disk('local')->assertExists($reviewer->worksheet_signature_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.worksheet_signatory_updated',
            'actor_user_id' => $reviewer->id,
        ]);

        $preview = $this->actingAs($reviewer)
            ->get(route('reviewer.settings.worksheet-signature.preview'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $preview->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));

        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $response = $this->actingAs($applicant)->get(route('reviewer.settings.worksheet-signature.preview'));
        $response->status() === 403 ? $response->assertForbidden() : $response->assertRedirect(route('dashboard'));
    }

    public function test_reviewer_enabled_adviser_can_configure_worksheets_from_adviser_settings(): void
    {
        Storage::fake('local');
        $reviewer = User::factory()->reviewer(['Technical'])->create();

        $this->actingAs($reviewer)
            ->get(route('adviser.settings.index', ['tab' => 'worksheet']))
            ->assertOk()
            ->assertSee('Worksheet Configuration')
            ->assertSee('Printed Reviewer Name')
            ->assertSee(route('adviser.settings.worksheet-signatory.update'), false)
            ->assertSee('Use a transparent background');

        $this->actingAs($reviewer)
            ->put(route('adviser.settings.worksheet-signatory.update'), [
                'worksheet_signatory_name' => 'Reviewer Adviser Signatory',
                'signature' => UploadedFile::fake()->image('adviser-signature.png', 420, 110),
            ])
            ->assertRedirect(route('adviser.settings.index', ['tab' => 'worksheet']))
            ->assertSessionDoesntHaveErrors();

        $reviewer->refresh();
        $this->assertSame('Reviewer Adviser Signatory', $reviewer->worksheet_signatory_name);
        $this->actingAs($reviewer)
            ->get(route('adviser.settings.worksheet-signature.preview'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_pure_adviser_cannot_see_or_invoke_reviewer_worksheet_configuration(): void
    {
        $adviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'reviewer_enabled' => false,
            'reviewer_capacity' => null,
            'reviewer_classifications' => null,
        ]);

        $this->actingAs($adviser)
            ->get(route('adviser.settings.index'))
            ->assertOk()
            ->assertDontSee('Worksheet Configuration')
            ->assertDontSee('Reviewer Capability')
            ->assertDontSee('Current Signature');

        $update = $this->actingAs($adviser)
            ->put(route('adviser.settings.worksheet-signatory.update'), [
                'worksheet_signatory_name' => 'Unauthorized Name',
            ]);
        $update->status() === 403 ? $update->assertForbidden() : $update->assertRedirect(route('dashboard'));
        $preview = $this->actingAs($adviser)->get(route('adviser.settings.worksheet-signature.preview'));
        $preview->status() === 403 ? $preview->assertForbidden() : $preview->assertRedirect(route('dashboard'));
        $this->assertNull($adviser->fresh()->worksheet_signatory_name);
    }
}
