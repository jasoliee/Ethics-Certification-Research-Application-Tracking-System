<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('review_form_submissions', 'completed_at')) {
            Schema::table('review_form_submissions', function (Blueprint $table): void {
                $table->timestamp('completed_at')->nullable()->after('review_date')->index();
            });
        }

        // Preserve submitted final records; only premature worksheet finalization becomes editable completion.
        DB::table('review_form_submissions')
            ->where('status', 'final')
            ->update(['completed_at' => DB::raw('COALESCE(finalized_at, updated_at)')]);
        $submittedAssignmentIds = DB::table('review_submissions')
            ->where('status', 'submitted')
            ->pluck('reviewer_assignment_id');
        DB::table('review_form_submissions')
            ->where('status', 'final')
            ->when($submittedAssignmentIds->isNotEmpty(), fn ($query) => $query->whereNotIn('reviewer_assignment_id', $submittedAssignmentIds))
            ->update([
                'status' => 'completed',
                'catalog_version' => null,
                'catalog_snapshot' => null,
                'finalized_payload_snapshot' => null,
                'finalized_context_snapshot' => null,
                'finalized_at' => null,
            ]);

        if (! Schema::hasColumn('application_decision_releases', 'source_review_submission_id')) {
            Schema::table('application_decision_releases', function (Blueprint $table): void {
                $table->foreignId('source_review_submission_id')->nullable()->after('source_review_type');
            });
        }
        if (! Schema::hasForeignKey('application_decision_releases', 'decision_release_source_review_fk')) {
            Schema::table('application_decision_releases', function (Blueprint $table): void {
                $table->foreign('source_review_submission_id', 'decision_release_source_review_fk')
                    ->references('id')
                    ->on('review_submissions')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('certificate_versions', 'regenerated_at')) {
            Schema::table('certificate_versions', function (Blueprint $table): void {
                $table->timestamp('regenerated_at')->nullable()->after('generated_at')->index();
            });
        }
        if (! Schema::hasColumn('certificate_versions', 'regeneration_reason')) {
            Schema::table('certificate_versions', function (Blueprint $table): void {
                $table->string('regeneration_reason', 40)->nullable()->after('regenerated_at')->index();
            });
        }
    }

    public function down(): void
    {
        if (DB::table('review_form_submissions')->where('status', 'completed')->exists()
            || DB::table('application_decision_releases')->whereNotNull('source_review_submission_id')->exists()
            || DB::table('certificate_versions')->whereNotNull('regenerated_at')->exists()) {
            throw new RuntimeException('Reviewer completion or release/certificate regeneration provenance exists; explicit archival handling is required before rollback.');
        }

        $sourceReviewForeign = DB::getDriverName() === 'sqlite'
            ? ['source_review_submission_id']
            : 'decision_release_source_review_fk';
        if (Schema::hasForeignKey('application_decision_releases', $sourceReviewForeign)) {
            Schema::table(
                'application_decision_releases',
                fn (Blueprint $table) => $table->dropForeign($sourceReviewForeign),
            );
        }
        if (Schema::hasColumn('application_decision_releases', 'source_review_submission_id')) {
            Schema::table('application_decision_releases', fn (Blueprint $table) => $table->dropColumn('source_review_submission_id'));
        }
        foreach (['regenerated_at', 'regeneration_reason'] as $column) {
            if (Schema::hasIndex('certificate_versions', [$column])) {
                Schema::table('certificate_versions', fn (Blueprint $table) => $table->dropIndex([$column]));
            }
        }
        Schema::table('certificate_versions', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['regenerated_at', 'regeneration_reason'],
                fn (string $column): bool => Schema::hasColumn('certificate_versions', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
        if (Schema::hasColumn('review_form_submissions', 'completed_at')) {
            if (Schema::hasIndex('review_form_submissions', ['completed_at'])) {
                Schema::table('review_form_submissions', fn (Blueprint $table) => $table->dropIndex(['completed_at']));
            }
            Schema::table('review_form_submissions', fn (Blueprint $table) => $table->dropColumn('completed_at'));
        }
    }
};
