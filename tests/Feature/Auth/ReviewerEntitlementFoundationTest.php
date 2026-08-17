<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\ReviewerAssignment;
use App\Models\ReviewerIdentityReconciliation;
use App\Models\User;
use Database\Seeders\TestingUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReviewerEntitlementFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_reviewer_conversion_preserves_identity_history_and_does_not_merge_namesakes(): void
    {
        $existingAdviser = User::factory()->create([
            'name' => 'Shared Person',
            'first_name' => 'Shared',
            'last_name' => 'Person',
            'role' => UserRole::Adviser,
        ]);
        $legacyReviewer = User::factory()->create([
            'name' => 'Shared Person',
            'first_name' => 'Shared',
            'last_name' => 'Person',
            'reviewer_classification' => 'Full Board',
        ]);
        DB::table('users')->where('id', $legacyReviewer->id)->update(['role' => UserRole::Reviewer->value]);
        $legacyReviewer->refresh();
        $assignment = ReviewerAssignment::factory()->create([
            'reviewer_user_id' => $legacyReviewer->id,
        ]);

        $migration = require database_path('migrations/2026_08_17_000000_add_reviewer_entitlement_to_users_table.php');
        $migration->up();

        $convertedReviewer = $legacyReviewer->fresh();

        $this->assertSame(UserRole::Adviser, $convertedReviewer->role);
        $this->assertTrue($convertedReviewer->reviewer_enabled);
        $this->assertSame(['Full Board'], $convertedReviewer->reviewer_classifications);
        $this->assertSame($legacyReviewer->id, $assignment->fresh()->reviewer_user_id);
        $this->assertDatabaseHas('users', ['id' => $existingAdviser->id]);
        $this->assertDatabaseHas('users', ['id' => $legacyReviewer->id]);
        $this->assertSame(2, User::query()->where('name', 'Shared Person')->count());

        $reconciliation = ReviewerIdentityReconciliation::query()->sole();
        $this->assertSame($legacyReviewer->id, $reconciliation->source_user_id);
        $this->assertSame($existingAdviser->id, $reconciliation->target_adviser_user_id);
        $this->assertSame(ReviewerIdentityReconciliation::STATUS_PENDING, $reconciliation->status);
        $this->assertSame(['name'], $reconciliation->matched_fields);
    }

    public function test_reviewer_entitlement_is_rechecked_from_the_database_on_every_request(): void
    {
        Route::middleware(['auth', 'reviewer.enabled'])
            ->get('/_testing/reviewer-entitlement', fn () => 'reviewer access')
            ->name('testing.reviewer-entitlement');

        $reviewerAdviser = User::factory()->reviewer(['Expedited', 'Full Board'])->create();

        $this->actingAs($reviewerAdviser)
            ->get('/_testing/reviewer-entitlement')
            ->assertOk()
            ->assertSee('reviewer access');

        // Deliberately bypass the in-memory model so it remains stale in the session.
        DB::table('users')->where('id', $reviewerAdviser->id)->update(['reviewer_enabled' => false]);

        $this->get('/_testing/reviewer-entitlement')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $reviewerAdviser->id,
            'action' => 'auth.reviewer_entitlement_denied',
        ]);
    }

    public function test_model_access_helper_requires_an_active_enabled_adviser(): void
    {
        $enabled = User::factory()->reviewer(['Expedited', 'Full Board'])->create();
        $disabled = User::factory()->reviewer()->create(['reviewer_enabled' => false]);
        $inactive = User::factory()->reviewer()->create(['account_status' => 'inactive']);
        $legacyRole = User::factory()->create([
            'reviewer_enabled' => true,
            'reviewer_classifications' => ['Expedited'],
        ]);
        DB::table('users')->where('id', $legacyRole->id)->update(['role' => UserRole::Reviewer->value]);
        $legacyRole->refresh();

        $this->assertTrue($enabled->hasReviewerAccess());
        $this->assertTrue($enabled->hasReviewerClassification('full_board'));
        $this->assertFalse($disabled->hasReviewerAccess());
        $this->assertFalse($inactive->hasReviewerAccess());
        $this->assertFalse($legacyRole->hasReviewerAccess());
        $this->assertSame([$enabled->id], User::query()->reviewerEnabled()->pluck('id')->all());
    }

    public function test_valid_legacy_standalone_reviewer_credentials_are_rejected_and_audited(): void
    {
        $legacyReviewer = User::factory()->create([
            'username' => 'legacy.reviewer',
            'password' => 'correct-password',
            'reviewer_enabled' => true,
        ]);
        DB::table('users')->where('id', $legacyReviewer->id)->update(['role' => UserRole::Reviewer->value]);
        $legacyReviewer->refresh();

        $this->from(route('login'))->post(route('login.store'), [
            'username' => $legacyReviewer->username,
            'password' => 'correct-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'credentials' => 'The username or password is incorrect.',
            ]);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_blocked_legacy_reviewer',
            'subject_id' => $legacyReviewer->id,
        ]);
    }

    public function test_testing_reviewer_identity_is_seeded_as_an_entitled_adviser_idempotently(): void
    {
        $this->seed(TestingUserSeeder::class);
        $this->seed(TestingUserSeeder::class);

        $reviewer = User::query()->where('username', 'reviewertest')->firstOrFail();

        $this->assertSame(1, User::query()->where('username', 'reviewertest')->count());
        $this->assertSame(UserRole::Adviser, $reviewer->role);
        $this->assertTrue($reviewer->hasReviewerAccess());
        $this->assertSame(['Expedited'], $reviewer->reviewer_classifications);

        $legacyFactoryOverride = User::factory()->create(['role' => UserRole::Reviewer]);
        $this->assertSame(UserRole::Adviser, $legacyFactoryOverride->role);
        $this->assertTrue($legacyFactoryOverride->hasReviewerAccess());
    }
}
