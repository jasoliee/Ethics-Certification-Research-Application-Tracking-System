<?php

namespace Tests\Feature\Identity;

use App\Enums\ReviewerAssignmentStatus;
use App\Enums\UserRole;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\Identity\AccountTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdviserReviewerEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_bulk_show_reviewer_updates_only_active_advisers_and_is_idempotent(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $activeAdviser = $this->adviser();
        $alreadyEnabled = $this->adviser(['reviewer_enabled' => true]);
        $inactiveAdviser = $this->adviser(['account_status' => 'inactive']);
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);

        $this->actingAs($resLead)->post(route('res.users.mass-action'), [
            'action' => 'show_reviewer',
            'user_ids' => [$activeAdviser->id, $alreadyEnabled->id, $inactiveAdviser->id, $applicant->id],
        ])->assertRedirect()
            ->assertSessionHas('status', '1 Adviser account updated; 3 unchanged or ineligible.');

        $this->assertTrue($activeAdviser->refresh()->reviewer_enabled);
        $this->assertTrue($alreadyEnabled->refresh()->reviewer_enabled);
        $this->assertFalse($inactiveAdviser->refresh()->reviewer_enabled);
        $this->assertFalse($applicant->refresh()->reviewer_enabled);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $resLead->id,
            'subject_id' => $activeAdviser->id,
            'action' => 'user.reviewer_access_enabled',
        ]);
        Notification::assertSentTo($activeAdviser, DashboardUpdateNotification::class);
        Notification::assertNotSentTo($alreadyEnabled, DashboardUpdateNotification::class);

        $this->actingAs($resLead)->post(route('res.users.mass-action'), [
            'action' => 'show_reviewer',
            'user_ids' => [$activeAdviser->id],
        ])->assertRedirect()
            ->assertSessionHas('status', '0 Adviser accounts updated; 1 unchanged or ineligible.');
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_hide_reviewer_requires_assignment_warning_confirmation_and_preserves_active_work(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $adviser = $this->adviser(['reviewer_enabled' => true]);
        $assignment = ReviewerAssignment::factory()->create([
            'reviewer_user_id' => $adviser,
            'assignment_status' => ReviewerAssignmentStatus::InReview,
        ]);

        $this->actingAs($resLead)->post(route('res.users.mass-action'), [
            'action' => 'hide_reviewer',
            'user_ids' => [$adviser->id],
        ])->assertRedirect()
            ->assertSessionHasErrors('action');
        $this->assertTrue($adviser->refresh()->reviewer_enabled);

        $this->actingAs($resLead)->post(route('res.users.mass-action'), [
            'action' => 'hide_reviewer',
            'user_ids' => [$adviser->id],
            'confirm_active_assignments' => true,
        ])->assertRedirect()
            ->assertSessionHas('status', '1 Adviser account updated; 0 unchanged or ineligible. 1 active review assignment preserved.');

        $this->assertFalse($adviser->refresh()->reviewer_enabled);
        $this->assertDatabaseHas('reviewer_assignments', [
            'id' => $assignment->id,
            'reviewer_user_id' => $adviser->id,
            'assignment_status' => ReviewerAssignmentStatus::InReview->value,
            'superseded_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $resLead->id,
            'subject_id' => $adviser->id,
            'action' => 'user.reviewer_access_disabled',
        ]);
        Notification::assertSentTo($adviser, DashboardUpdateNotification::class);
    }

    public function test_reviewer_is_not_a_creatable_or_importable_account_type(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->assertNotContains(
            'reviewer',
            collect(app(AccountTypeCatalog::class)->allowedFor($resLead))->pluck('key')->all(),
        );

        $this->actingAs($resLead)
            ->get(route('res.users.import.form', ['account_type' => 'reviewer']))
            ->assertForbidden();

        $this->actingAs($resLead)->post(route('res.users.store'), [
            ...$this->profilePayload(),
            'role' => UserRole::Reviewer->value,
        ])->assertForbidden();
    }

    public function test_adviser_reviewer_profile_manages_capability_and_capacity_without_using_legacy_classifications(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $adviser = $this->adviser([
            'reviewer_enabled' => true,
            'reviewer_classification' => 'Expedited',
            'reviewer_classifications' => ['Expedited'],
            'reviewer_capacity' => 4,
        ]);

        $this->actingAs($resLead)
            ->get(route('res.users.edit', $adviser))
            ->assertOk()
            ->assertSee('name="reviewer_enabled"', false)
            ->assertDontSee('Reviewer Classifications');

        $this->actingAs($resLead)->put(route('res.users.update', $adviser), [
            ...$this->profilePayload($adviser),
            'reviewer_enabled' => '1',
            'reviewer_capacity' => 7,
        ])->assertRedirect(route('res.users.show', $adviser));

        $adviser->refresh();
        $this->assertTrue($adviser->reviewer_enabled);
        // Historical fields remain intact but no longer participate in current eligibility.
        $this->assertSame(['Expedited'], $adviser->reviewer_classifications);
        $this->assertSame('Expedited', $adviser->reviewer_classification);
        $this->assertSame(7, $adviser->reviewer_capacity);

        $this->actingAs($resLead)
            ->get(route('res.users.show', $adviser))
            ->assertOk()
            ->assertSee('Reviewer Access')
            ->assertDontSee('Reviewer Classifications')
            ->assertSee('Active Review Load')
            ->assertSee('Eligible for assignment');
    }

    public function test_phone_is_required_and_must_be_exactly_eleven_numeric_digits_on_create_and_edit(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        foreach ([null, '0917123456', '091712345678', '09171ABC567'] as $index => $phone) {
            $this->actingAs($resLead)->post(route('res.users.store'), [
                ...$this->profilePayload(),
                'email' => "invalid-phone-{$index}@ecrats.test",
                'institutional_identifier' => "KLD-EMP-PHONE-{$index}",
                'phone_number' => $phone,
                'role' => UserRole::Adviser->value,
            ])->assertSessionHasErrors('phone_number');
        }

        $adviser = $this->adviser();
        $this->actingAs($resLead)->put(route('res.users.update', $adviser), [
            ...$this->profilePayload($adviser),
            'phone_number' => '0917123456',
        ])->assertSessionHasErrors('phone_number');
    }

    /** @param array<string, mixed> $overrides */
    private function adviser(array $overrides = []): User
    {
        return User::factory()->create([
            'role' => UserRole::Adviser,
            'applicant_type' => null,
            'institutional_identifier' => strtoupper(fake()->unique()->bothify('KLD-ENT-####??')),
            'phone_number' => '09171234567',
            'position_title' => 'Research Adviser',
            'reviewer_enabled' => false,
            'reviewer_classification' => 'Expedited',
            'reviewer_classifications' => ['Expedited'],
            'reviewer_capacity' => 6,
            ...$overrides,
        ]);
    }

    /** @return array<string, mixed> */
    private function profilePayload(?User $user = null): array
    {
        return [
            'first_name' => $user?->first_name ?? 'Reviewer',
            'middle_name' => $user?->middle_name,
            'last_name' => $user?->last_name ?? 'Adviser',
            'suffix' => $user?->suffix,
            'email' => $user?->email ?? 'reviewer.adviser@ecrats.test',
            'institutional_identifier' => $user?->institutional_identifier ?? 'KLD-EMP-REVIEWER-1',
            'phone_number' => $user?->phone_number ?? '09171234567',
            'institution' => $user?->institution ?? 'Institute of Engineering',
            'program' => null,
            'year_level' => null,
            'position_title' => $user?->position_title ?? 'Research Adviser',
            'applicant_type' => null,
        ];
    }
}
