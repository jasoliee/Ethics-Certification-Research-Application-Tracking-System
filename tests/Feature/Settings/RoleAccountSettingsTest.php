<?php

namespace Tests\Feature\Settings;

use App\Enums\ApplicantType;
use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ProfileOption;
use App\Models\User;
use App\Services\Settings\SelfAccountSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_can_edit_only_permitted_profile_fields_and_view_real_profile_data(): void
    {
        $yearLevel = ProfileOption::query()->where('field', ProfileOptionField::YearLevel->value)->value('value');
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
            'first_name' => 'Before',
            'last_name' => 'Researcher',
            'institutional_identifier' => 'KLD-STU-STABLE-01',
            'username' => 'stable.researcher',
            'phone_number' => '09170001111',
            'institution' => 'Institute of Computing and Digital Innovation',
            'department' => 'Computer Studies',
            'year_level' => $yearLevel,
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.settings.index'))
            ->assertOk()
            ->assertSee('Editable Profile Information')
            ->assertSee('Security and Privacy')
            ->assertSee(route('applicant.settings.profile.update'), false);

        $this->actingAs($applicant)
            ->put(route('applicant.settings.profile.update'), [
                'first_name' => 'Updated',
                'middle_name' => 'A',
                'last_name' => 'Changed',
                'suffix' => null,
                'phone_number' => '09171234567',
                'institution' => $applicant->institution,
                'department' => $applicant->department,
                'program' => null,
                'year_level' => $yearLevel,
                'role' => UserRole::ResLead->value,
                'reviewer_enabled' => true,
                'reviewer_capacity' => 30,
                'institutional_identifier' => 'FORGED-STUDENT-ID',
                'username' => 'forged.username',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $applicant->refresh();
        $this->assertSame('Updated A Changed', $applicant->name);
        $this->assertSame('stable.researcher', $applicant->username);
        $this->assertSame('KLD-STU-STABLE-01', $applicant->institutional_identifier);
        $this->assertSame(UserRole::Applicant, $applicant->role);
        $this->assertFalse($applicant->reviewer_enabled);
        $this->assertNull($applicant->reviewer_capacity);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $applicant->id,
            'action' => 'settings.profile_updated',
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.profile.show'))
            ->assertOk()
            ->assertSee('Updated A Changed')
            ->assertSee($applicant->institutional_identifier)
            ->assertSee('09171234567')
            ->assertSee($applicant->institution)
            ->assertSee($applicant->department)
            ->assertSee('Edit Permitted Fields');
    }

    public function test_adviser_settings_store_expected_endorsements_but_keep_reviewer_fields_read_only(): void
    {
        $adviser = User::factory()->reviewer(['Expedited'])->create([
            'position_title' => 'Research Adviser',
            'reviewer_capacity' => 6,
        ]);

        $this->actingAs($adviser)
            ->put(route('adviser.settings.profile.update'), [
                'first_name' => $adviser->first_name,
                'middle_name' => $adviser->middle_name,
                'last_name' => $adviser->last_name,
                'suffix' => $adviser->suffix,
                'phone_number' => '09181234567',
                'institution' => $adviser->institution,
                'department' => $adviser->department,
                'position_title' => 'Senior Research Adviser',
                'expected_endorsement_count' => 12,
                'reviewer_capacity' => 99,
                'reviewer_classifications' => ['Full Board'],
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $adviser->refresh();
        $this->assertSame(12, $adviser->expected_endorsement_count);
        $this->assertSame(6, $adviser->reviewer_capacity);
        $this->assertSame(['Expedited'], $adviser->reviewerClassificationLabels());

        $this->actingAs($adviser)
            ->get(route('adviser.settings.index'))
            ->assertOk()
            ->assertSee('Endorsement Overview')
            ->assertSee('Reviewer Capability')
            ->assertSee('These eligibility fields are read-only');
        $this->actingAs($adviser)
            ->get(route('adviser.profile.show'))
            ->assertOk()
            ->assertSee('09181234567')
            ->assertSee($adviser->institution)
            ->assertSee($adviser->department)
            ->assertSee('Senior Research Adviser');
    }

    public function test_email_and_password_changes_require_current_password_and_are_audited(): void
    {
        $applicant = User::factory()->create([
            'password' => Hash::make('current-password'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($applicant)
            ->patch(route('applicant.settings.email.update'), [
                'current_password' => 'wrong-password',
                'email' => 'updated@example.test',
            ])
            ->assertSessionHasErrors('current_password');
        $this->assertNotSame('updated@example.test', $applicant->refresh()->email);

        $this->actingAs($applicant)
            ->patch(route('applicant.settings.email.update'), [
                'current_password' => 'current-password',
                'email' => 'updated@example.test',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('updated@example.test', $applicant->refresh()->email);
        $this->assertNull($applicant->email_verified_at);

        $this->actingAs($applicant)
            ->patch(route('applicant.settings.password.update'), [
                'current_password' => 'current-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
        $this->assertTrue(Hash::check('new-secure-password', $applicant->refresh()->password));
        $this->assertSame(1, AuditLog::query()->where('action', 'settings.email_updated')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'settings.password_updated')->count());
    }

    public function test_database_backed_password_change_revokes_only_other_sessions(): void
    {
        config(['session.driver' => 'database', 'session.table' => 'sessions']);
        $user = User::factory()->create();
        foreach (['current-login', 'other-login'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => 'test',
                'last_activity' => now()->timestamp,
            ]);
        }

        app(SelfAccountSettingsService::class)->updatePassword($user, 'another-secure-password', 'current-login');

        $this->assertDatabaseHas('sessions', ['id' => 'current-login', 'user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-login']);
        $this->assertSame(1, AuditLog::query()->where('action', 'settings.password_updated')->count());
    }

    public function test_security_changes_have_a_dedicated_rate_limit(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($user)
                ->patch(route('applicant.settings.email.update'), [
                    'current_password' => 'wrong-password',
                    'email' => "attempt-{$attempt}@example.test",
                ])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->patch(route('applicant.settings.email.update'), [
                'current_password' => 'wrong-password',
                'email' => 'rate-limited@example.test',
            ])
            ->assertStatus(429);
    }
}
