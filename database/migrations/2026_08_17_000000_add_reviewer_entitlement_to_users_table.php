<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the DDL resumable because MySQL commits each ALTER TABLE independently.
        if (! Schema::hasColumn('users', 'reviewer_enabled')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('reviewer_enabled')->default(false)->after('account_status');
            });
        }

        if (! Schema::hasColumn('users', 'reviewer_classifications')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('reviewer_classifications')->nullable()->after('reviewer_classification');
            });
        }

        if (! Schema::hasIndex('users', 'users_reviewer_entitlement_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index(
                    ['role', 'account_status', 'reviewer_enabled'],
                    'users_reviewer_entitlement_index',
                );
            });
        }

        if (! Schema::hasTable('reviewer_identity_reconciliations')) {
            Schema::create('reviewer_identity_reconciliations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('source_user_id');
                $table->unsignedBigInteger('target_adviser_user_id');
                $table->string('status', 30)->default('pending')->index();
                $table->json('matched_fields');
                $table->text('reason')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->unsignedBigInteger('resolved_by_user_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->foreign('source_user_id', 'reviewer_reconciliation_source_fk')
                    ->references('id')->on('users')->restrictOnDelete();
                $table->foreign('target_adviser_user_id', 'reviewer_reconciliation_target_fk')
                    ->references('id')->on('users')->restrictOnDelete();
                $table->foreign('resolved_by_user_id', 'reviewer_reconciliation_resolver_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->unique(
                    ['source_user_id', 'target_adviser_user_id'],
                    'reviewer_reconciliation_pair_unique',
                );
            });
        }

        // Normalize each legacy Reviewer in place. Keeping the same users.id preserves all
        // assignments, submissions, comments, notifications, and audit relationships.
        // Possible duplicate people are intentionally not guessed at or merged here.
        DB::transaction(function (): void {
            // Freeze the pre-existing Adviser set before converting any Reviewer so two
            // legacy Reviewer rows can never be suggested as merge targets for each other.
            $existingAdvisers = DB::table('users')
                ->where('role', UserRole::Adviser->value)
                ->select(['id', 'name', 'email', 'institutional_identifier'])
                ->get();

            DB::table('users')
                ->where('role', UserRole::Reviewer->value)
                ->select(['id', 'name', 'email', 'institutional_identifier', 'reviewer_classification'])
                ->orderBy('id')
                ->chunkById(100, function ($reviewers) use ($existingAdvisers): void {
                    foreach ($reviewers as $reviewer) {
                        $this->recordPotentialIdentityMatches($reviewer, $existingAdvisers);

                        $classification = trim((string) $reviewer->reviewer_classification);

                        DB::table('users')
                            ->where('id', $reviewer->id)
                            ->where('role', UserRole::Reviewer->value)
                            ->update([
                                'role' => UserRole::Adviser->value,
                                'reviewer_enabled' => true,
                                'reviewer_classifications' => $classification === ''
                                    ? null
                                    : json_encode([$classification], JSON_THROW_ON_ERROR),
                            ]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Role normalization cannot be reversed safely: after deployment there is no
        // reliable way to distinguish migrated reviewers from newly entitled Advisers.
        // Refuse to erase entitlement/reconciliation data once the feature is in use.
        $hasEntitlementData = Schema::hasColumn('users', 'reviewer_enabled')
            && DB::table('users')->where('reviewer_enabled', true)->exists();
        $hasClassificationData = Schema::hasColumn('users', 'reviewer_classifications')
            && DB::table('users')->whereNotNull('reviewer_classifications')->exists();
        $hasReconciliationData = Schema::hasTable('reviewer_identity_reconciliations')
            && DB::table('reviewer_identity_reconciliations')->exists();

        if ($hasEntitlementData || $hasClassificationData || $hasReconciliationData) {
            throw new RuntimeException(
                'Reviewer entitlement consolidation cannot be rolled back after entitlement or reconciliation data exists.',
            );
        }

        Schema::dropIfExists('reviewer_identity_reconciliations');

        if (Schema::hasIndex('users', 'users_reviewer_entitlement_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_reviewer_entitlement_index');
            });
        }

        foreach (['reviewer_classifications', 'reviewer_enabled'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Record conservative candidates without changing either identity.
     *
     * @param  Collection<int, object>  $existingAdvisers
     */
    private function recordPotentialIdentityMatches(object $reviewer, $existingAdvisers): void
    {
        foreach ($existingAdvisers as $adviser) {
            $matchedFields = [];

            foreach (['email', 'institutional_identifier', 'name'] as $field) {
                $sourceValue = $this->normalizedIdentityValue($reviewer->{$field} ?? null);
                $targetValue = $this->normalizedIdentityValue($adviser->{$field} ?? null);

                if ($sourceValue !== null && hash_equals($sourceValue, (string) $targetValue)) {
                    $matchedFields[] = $field;
                }
            }

            if ($matchedFields === []) {
                continue;
            }

            DB::table('reviewer_identity_reconciliations')->insertOrIgnore([
                'source_user_id' => $reviewer->id,
                'target_adviser_user_id' => $adviser->id,
                'status' => 'pending',
                'matched_fields' => json_encode($matchedFields, JSON_THROW_ON_ERROR),
                'reason' => 'Potential duplicate detected during legacy Reviewer-to-Adviser consolidation; manual RES review is required.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function normalizedIdentityValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($value)));

        return filled($normalized) ? $normalized : null;
    }
};
