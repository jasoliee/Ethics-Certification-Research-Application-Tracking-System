<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('review_submission_versions')) {
            Schema::create('review_submission_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('review_submission_id');
                $table->foreign('review_submission_id', 'rsv_submission_fk')
                    ->references('id')->on('review_submissions')->cascadeOnDelete();
                $table->foreignId('reviewer_assignment_id');
                $table->foreign('reviewer_assignment_id', 'rsv_assignment_fk')
                    ->references('id')->on('reviewer_assignments')->restrictOnDelete();
                $table->unsignedInteger('version_number');
                $table->uuid('submission_token');
                $table->string('decision', 30);
                $table->text('decision_comment')->nullable();
                $table->unsignedSmallInteger('snapshot_schema_version')->default(1);
                $table->json('payload_snapshot');
                $table->char('payload_sha256', 64);
                $table->char('request_sha256', 64);
                $table->foreignId('submitted_by_user_id')->nullable();
                $table->foreign('submitted_by_user_id', 'rsv_submitter_fk')
                    ->references('id')->on('users')->restrictOnDelete();
                $table->timestamp('submitted_at')->index();
                $table->timestamps();

                $table->unique(['review_submission_id', 'version_number'], 'rsv_submission_version_unique');
                $table->unique(['review_submission_id', 'submission_token'], 'rsv_submission_token_unique');
                $table->index(['reviewer_assignment_id', 'submitted_at'], 'rsv_assignment_submitted_idx');
            });
        }

        Schema::table('review_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('review_submissions', 'current_version_id')) {
                $table->unsignedBigInteger('current_version_id')->nullable()->after('reviewer_assignment_id');
            }
            if (! Schema::hasColumn('review_submissions', 'draft_decision')) {
                $table->string('draft_decision', 30)->nullable()->after('decision_comment');
            }
            if (! Schema::hasColumn('review_submissions', 'draft_decision_comment')) {
                $table->text('draft_decision_comment')->nullable()->after('draft_decision');
            }
            if (! Schema::hasColumn('review_submissions', 'has_unsubmitted_changes')) {
                $table->boolean('has_unsubmitted_changes')->default(false)->after('draft_decision_comment')->index();
            }
        });

        Schema::table('review_form_artifacts', function (Blueprint $table): void {
            if (! Schema::hasColumn('review_form_artifacts', 'review_submission_version_id')) {
                $table->unsignedBigInteger('review_submission_version_id')->nullable()->after('review_form_submission_id')->index();
            }
        });

        Schema::table('review_comments', function (Blueprint $table): void {
            if (! Schema::hasColumn('review_comments', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('research_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('research_applications', 'review_consensus_status')) {
                $table->string('review_consensus_status', 30)->nullable()->after('current_revision_cycle')->index();
            }
            if (! Schema::hasColumn('research_applications', 'review_consensus_cycle')) {
                $table->unsignedSmallInteger('review_consensus_cycle')->nullable()->after('review_consensus_status');
            }
            if (! Schema::hasColumn('research_applications', 'review_consensus_decision')) {
                $table->string('review_consensus_decision', 30)->nullable()->after('review_consensus_cycle');
            }
            if (! Schema::hasColumn('research_applications', 'review_consensus_signature')) {
                $table->char('review_consensus_signature', 64)->nullable()->after('review_consensus_decision');
            }
            if (! Schema::hasColumn('research_applications', 'review_consensus_evaluated_at')) {
                $table->timestamp('review_consensus_evaluated_at')->nullable()->after('review_consensus_signature');
            }
            if (! Schema::hasColumn('research_applications', 'review_conflicted_at')) {
                $table->timestamp('review_conflicted_at')->nullable()->after('review_consensus_evaluated_at');
            }
        });

        Schema::table('application_decision_releases', function (Blueprint $table): void {
            if (! Schema::hasColumn('application_decision_releases', 'source_review_submission_version_id')) {
                $table->unsignedBigInteger('source_review_submission_version_id')->nullable()->after('source_review_submission_id');
            }
            if (! Schema::hasColumn('application_decision_releases', 'review_consensus_signature')) {
                $table->char('review_consensus_signature', 64)->nullable()->after('decision');
            }
            if (! Schema::hasColumn('application_decision_releases', 'released_feedback_snapshot')) {
                $table->json('released_feedback_snapshot')->nullable()->after('review_consensus_signature');
            }
        });

        $this->backfillSubmittedReviews();

        if (! Schema::hasForeignKey('review_submissions', 'review_submission_current_version_fk')) {
            Schema::table('review_submissions', function (Blueprint $table): void {
                $table->foreign('current_version_id', 'review_submission_current_version_fk')
                    ->references('id')->on('review_submission_versions')->restrictOnDelete();
            });
        }
        if (! Schema::hasForeignKey('review_form_artifacts', 'review_artifact_submission_version_fk')) {
            Schema::table('review_form_artifacts', function (Blueprint $table): void {
                $table->foreign('review_submission_version_id', 'review_artifact_submission_version_fk')
                    ->references('id')->on('review_submission_versions')->restrictOnDelete();
            });
        }
        if (! Schema::hasForeignKey('application_decision_releases', 'decision_release_source_version_fk')) {
            Schema::table('application_decision_releases', function (Blueprint $table): void {
                $table->foreign('source_review_submission_version_id', 'decision_release_source_version_fk')
                    ->references('id')->on('review_submission_versions')->restrictOnDelete();
            });
        }
    }

    private function backfillSubmittedReviews(): void
    {
        DB::table('review_submissions')
            ->where('status', 'submitted')
            ->whereNotNull('decision')
            ->orderBy('id')
            ->each(function (object $submission): void {
                if (DB::table('review_submission_versions')->where('review_submission_id', $submission->id)->exists()) {
                    return;
                }

                $assignment = DB::table('reviewer_assignments')->where('id', $submission->reviewer_assignment_id)->first();
                if (! $assignment) {
                    return;
                }

                $forms = DB::table('review_form_submissions')
                    ->where('reviewer_assignment_id', $assignment->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (object $form): array => [
                        'id' => $form->id,
                        'form_type' => $form->form_type,
                        'catalog_version' => $form->catalog_version ?? null,
                        'catalog_snapshot' => $this->decodeJson($form->catalog_snapshot ?? null),
                        'payload' => $this->decodeJson($form->finalized_payload_snapshot ?? null),
                        'context' => $this->decodeJson($form->finalized_context_snapshot ?? null),
                    ])->all();
                $comments = DB::table('review_comments')
                    ->where('reviewer_assignment_id', $assignment->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (object $comment): array => [
                        'id' => $comment->id,
                        'application_document_id' => $comment->application_document_id,
                        'scope' => $comment->scope,
                        'category' => $comment->category,
                        'page_number' => $comment->page_number,
                        'status' => $comment->status ?? 'open',
                        'body' => $comment->body,
                        'created_at' => $comment->created_at,
                        'updated_at' => $comment->updated_at,
                    ])->all();
                $artifacts = DB::table('review_form_artifacts')
                    ->join('review_form_submissions', 'review_form_submissions.id', '=', 'review_form_artifacts.review_form_submission_id')
                    ->where('review_form_submissions.reviewer_assignment_id', $assignment->id)
                    ->orderBy('review_form_artifacts.id')
                    ->get(['review_form_artifacts.id', 'review_form_artifacts.artifact_version', 'review_form_artifacts.sha256'])
                    ->map(fn (object $artifact): array => (array) $artifact)
                    ->all();
                $snapshot = [
                    'schema_version' => 1,
                    'assignment_id' => $assignment->id,
                    'review_cycle' => (int) $assignment->review_cycle,
                    'review_type' => $assignment->review_type,
                    'decision' => $submission->decision,
                    'decision_comment' => $submission->decision_comment,
                    'forms' => $forms,
                    'comments' => $comments,
                    'artifacts' => $artifacts,
                ];
                $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $submittedAt = $submission->submitted_at ?? $submission->updated_at;
                $versionId = DB::table('review_submission_versions')->insertGetId([
                    'review_submission_id' => $submission->id,
                    'reviewer_assignment_id' => $assignment->id,
                    'version_number' => 1,
                    'submission_token' => (string) Str::uuid(),
                    'decision' => $submission->decision,
                    'decision_comment' => $submission->decision_comment,
                    'snapshot_schema_version' => 1,
                    'payload_snapshot' => $encoded,
                    'payload_sha256' => hash('sha256', $encoded),
                    'request_sha256' => hash('sha256', $encoded),
                    'submitted_by_user_id' => $assignment->reviewer_user_id,
                    'submitted_at' => $submittedAt,
                    'created_at' => $submittedAt,
                    'updated_at' => $submittedAt,
                ]);

                DB::table('review_submissions')->where('id', $submission->id)->update([
                    'current_version_id' => $versionId,
                    'draft_decision' => $submission->decision,
                    'draft_decision_comment' => $submission->decision_comment,
                    'has_unsubmitted_changes' => false,
                ]);
                DB::table('review_form_artifacts')
                    ->whereIn('id', collect($artifacts)->pluck('id'))
                    ->update(['review_submission_version_id' => $versionId]);
                DB::table('application_decision_releases')
                    ->where('source_review_submission_id', $submission->id)
                    ->whereNull('source_review_submission_version_id')
                    ->update(['source_review_submission_version_id' => $versionId]);
            });
    }

    /** @return array<string, mixed>|array<int, mixed>|null */
    private function decodeJson(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function down(): void
    {
        if (Schema::hasTable('review_submission_versions')
            && (DB::table('review_submission_versions')->where('version_number', '>', 1)->exists()
                || DB::table('application_decision_releases')->whereNotNull('source_review_submission_version_id')->exists())) {
            throw new RuntimeException('Versioned reviewer evidence or release provenance exists; archive it explicitly before rollback.');
        }

        foreach ([
            ['application_decision_releases', 'decision_release_source_version_fk'],
            ['review_form_artifacts', 'review_artifact_submission_version_fk'],
            ['review_submissions', 'review_submission_current_version_fk'],
        ] as [$table, $foreign]) {
            if (Schema::hasForeignKey($table, $foreign)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($foreign));
            }
        }

        Schema::table('application_decision_releases', function (Blueprint $table): void {
            $columns = array_filter([
                'source_review_submission_version_id',
                'review_consensus_signature',
                'released_feedback_snapshot',
            ], fn (string $column): bool => Schema::hasColumn('application_decision_releases', $column));
            if ($columns !== []) {
                $table->dropColumn(array_values($columns));
            }
        });
        Schema::table('research_applications', function (Blueprint $table): void {
            $columns = array_filter([
                'review_consensus_status',
                'review_consensus_cycle',
                'review_consensus_decision',
                'review_consensus_signature',
                'review_consensus_evaluated_at',
                'review_conflicted_at',
            ], fn (string $column): bool => Schema::hasColumn('research_applications', $column));
            if ($columns !== []) {
                $table->dropColumn(array_values($columns));
            }
        });
        if (Schema::hasColumn('review_comments', 'deleted_at')) {
            Schema::table('review_comments', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
        if (Schema::hasColumn('review_form_artifacts', 'review_submission_version_id')) {
            Schema::table('review_form_artifacts', fn (Blueprint $table) => $table->dropColumn('review_submission_version_id'));
        }
        Schema::table('review_submissions', function (Blueprint $table): void {
            $columns = array_filter([
                'current_version_id',
                'draft_decision',
                'draft_decision_comment',
                'has_unsubmitted_changes',
            ], fn (string $column): bool => Schema::hasColumn('review_submissions', $column));
            if ($columns !== []) {
                $table->dropColumn(array_values($columns));
            }
        });
        Schema::dropIfExists('review_submission_versions');
    }
};
