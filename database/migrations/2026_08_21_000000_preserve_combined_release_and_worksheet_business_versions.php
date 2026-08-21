<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('application_decision_releases', 'source_review_submission_version_ids')) {
            Schema::table('application_decision_releases', function (Blueprint $table): void {
                $table->json('source_review_submission_version_ids')
                    ->nullable()
                    ->after('source_review_submission_version_id');
            });
        }

        DB::table('application_decision_releases')
            ->whereNull('source_review_submission_version_ids')
            ->whereNotNull('source_review_submission_version_id')
            ->select(['id', 'source_review_submission_version_id'])
            ->orderBy('id')
            ->chunkById(200, function ($releases): void {
                foreach ($releases as $release) {
                    DB::table('application_decision_releases')
                        ->where('id', $release->id)
                        ->update([
                            'source_review_submission_version_ids' => json_encode(
                                [(int) $release->source_review_submission_version_id],
                                JSON_THROW_ON_ERROR,
                            ),
                        ]);
                }
            });

        if (! Schema::hasColumn('review_form_artifacts', 'business_version')) {
            Schema::table('review_form_artifacts', function (Blueprint $table): void {
                // Nullable protects genuinely orphaned legacy rows; every current
                // generated artifact writes the cycle-derived value explicitly.
                $table->unsignedSmallInteger('business_version')->nullable()->after('artifact_version');
                $table->index(
                    ['review_form_submission_id', 'business_version'],
                    'review_form_artifact_business_version_index',
                );
            });
        }

        DB::table('review_form_artifacts')
            ->join(
                'review_form_submissions',
                'review_form_submissions.id',
                '=',
                'review_form_artifacts.review_form_submission_id',
            )
            ->join(
                'reviewer_assignments',
                'reviewer_assignments.id',
                '=',
                'review_form_submissions.reviewer_assignment_id',
            )
            ->whereNull('review_form_artifacts.business_version')
            ->select([
                'review_form_artifacts.id',
                'reviewer_assignments.review_cycle',
            ])
            ->orderBy('review_form_artifacts.id')
            ->chunkById(200, function ($artifacts): void {
                foreach ($artifacts as $artifact) {
                    DB::table('review_form_artifacts')
                        ->where('id', $artifact->id)
                        ->update(['business_version' => ((int) $artifact->review_cycle) + 1]);
                }
            }, 'review_form_artifacts.id', 'id');
    }

    public function down(): void
    {
        $hasCombinedRelease = DB::table('application_decision_releases')
            ->whereNotNull('source_review_submission_version_ids')
            ->select(['source_review_submission_version_ids'])
            ->get()
            ->contains(function ($release): bool {
                $ids = json_decode((string) $release->source_review_submission_version_ids, true);

                return is_array($ids) && count($ids) > 1;
            });

        if ($hasCombinedRelease) {
            throw new RuntimeException(
                'Combined Full Board release provenance exists; use a forward migration instead of discarding its source-version set.',
            );
        }

        if (Schema::hasColumn('review_form_artifacts', 'business_version')) {
            Schema::table('review_form_artifacts', function (Blueprint $table): void {
                if (Schema::hasIndex('review_form_artifacts', 'review_form_artifact_business_version_index')) {
                    $table->dropIndex('review_form_artifact_business_version_index');
                }
                $table->dropColumn('business_version');
            });
        }

        if (Schema::hasColumn('application_decision_releases', 'source_review_submission_version_ids')) {
            Schema::table('application_decision_releases', function (Blueprint $table): void {
                $table->dropColumn('source_review_submission_version_ids');
            });
        }
    }
};
