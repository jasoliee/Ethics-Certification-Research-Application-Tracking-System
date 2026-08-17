<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\ReviewerIdentityReconciliation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerIdentityReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_can_merge_preserved_reviewer_history_without_deleting_source_identity(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $source = User::factory()->reviewer(['Expedited'])->create();
        $target = User::factory()->create([
            'role' => UserRole::Adviser,
            'reviewer_enabled' => false,
        ]);
        $application = ResearchApplication::factory()->create();
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application,
            'reviewer_user_id' => $source,
        ]);
        $candidate = ReviewerIdentityReconciliation::create([
            'source_user_id' => $source->id,
            'target_adviser_user_id' => $target->id,
            'matched_fields' => ['name'],
            'reason' => 'Test candidate.',
        ]);

        $this->actingAs($resLead)
            ->post(route('res.users.reviewer-reconciliations.merge', $candidate), [
                'confirm_merge' => '1',
                'resolution_notes' => 'Confirmed from institutional records.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($target->id, $assignment->fresh()->reviewer_user_id);
        $this->assertDatabaseHas('users', [
            'id' => $source->id,
            'account_status' => AccountStatus::Inactive->value,
            'reviewer_enabled' => false,
            'deleted_at' => null,
        ]);
        $this->assertTrue($target->fresh()->reviewer_enabled);
        $this->assertSame(ReviewerIdentityReconciliation::STATUS_MERGED, $candidate->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $resLead->id,
            'action' => 'user.reviewer_identity_merged',
        ]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $target->id]);
    }

    public function test_res_can_confirm_candidates_are_distinct_and_non_res_cannot_resolve_them(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $source = User::factory()->reviewer()->create();
        $target = User::factory()->create(['role' => UserRole::Adviser]);
        $candidate = ReviewerIdentityReconciliation::create([
            'source_user_id' => $source->id,
            'target_adviser_user_id' => $target->id,
            'matched_fields' => ['name'],
        ]);

        $this->actingAs($target)
            ->post(route('res.users.reviewer-reconciliations.keep-separate', $candidate))
            ->assertRedirect(route('dashboard'));
        $this->assertSame(ReviewerIdentityReconciliation::STATUS_PENDING, $candidate->fresh()->status);

        $this->actingAs($resLead)
            ->post(route('res.users.reviewer-reconciliations.keep-separate', $candidate))
            ->assertSessionHasNoErrors();
        $this->assertSame(ReviewerIdentityReconciliation::STATUS_KEPT_SEPARATE, $candidate->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'user.reviewer_identity_kept_separate')->exists());
    }
}
