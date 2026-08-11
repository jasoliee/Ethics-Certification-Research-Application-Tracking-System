<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_decision_releases')) {
            Schema::create('application_decision_releases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('research_application_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('review_cycle')->default(0);
                $table->string('source_review_type', 30);
                $table->string('decision', 30)->index();
                $table->foreignId('released_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('released_at')->index();
                $table->timestamps();

                $table->unique(
                    ['research_application_id', 'review_cycle'],
                    'application_decision_release_cycle_unique',
                );
            });
        }

        if (! Schema::hasTable('application_revisions')) {
            Schema::create('application_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('research_application_id')->constrained()->cascadeOnDelete();
                $table->foreignId('application_decision_release_id')
                    ->unique()
                    ->constrained('application_decision_releases')
                    ->restrictOnDelete();
                $table->unsignedSmallInteger('revision_number');
                $table->string('status', 30)->index();
                $table->timestamp('due_at')->index();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['research_application_id', 'revision_number'],
                    'application_revision_number_unique',
                );
            });
        }

        if (! Schema::hasTable('application_revision_requirements')) {
            Schema::create('application_revision_requirements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('application_revision_id');
                $table->foreign('application_revision_id', 'arr_revision_fk')
                    ->references('id')
                    ->on('application_revisions')
                    ->cascadeOnDelete();
                $table->foreignId('document_requirement_id');
                $table->foreign('document_requirement_id', 'arr_requirement_fk')
                    ->references('id')
                    ->on('document_requirements')
                    ->restrictOnDelete();
                $table->foreignId('source_application_document_id')
                    ->nullable();
                $table->foreign('source_application_document_id', 'arr_source_document_fk')
                    ->references('id')
                    ->on('application_documents')
                    ->nullOnDelete();
                $table->foreignId('replacement_application_document_id')
                    ->nullable();
                $table->foreign('replacement_application_document_id', 'arr_replacement_document_fk')
                    ->references('id')
                    ->on('application_documents')
                    ->nullOnDelete();
                $table->boolean('is_required')->default(true)->index();
                $table->timestamps();

                $table->unique(
                    ['application_revision_id', 'document_requirement_id'],
                    'application_revision_requirement_unique',
                );
            });
        }

        if (! Schema::hasTable('applicant_survey_responses')) {
            Schema::create('applicant_survey_responses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('research_application_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('applicant_user_id')->constrained('users')->restrictOnDelete();
                $table->json('ratings');
                $table->text('positive_feedback');
                $table->text('improvement_feedback');
                $table->text('additional_comments')->nullable();
                $table->timestamp('completed_at')->index();
                $table->timestamps();

                $table->index(['applicant_user_id', 'completed_at']);
            });
        }

        if (! Schema::hasTable('certificate_backgrounds')) {
            Schema::create('certificate_backgrounds', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('asset_version')->unique();
                $table->string('source_kind', 30)->index();
                $table->string('original_file_name');
                $table->string('stored_file_path');
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('file_size_bytes');
                $table->char('sha256', 64)->index();
                $table->unsignedInteger('width_pixels')->nullable();
                $table->unsignedInteger('height_pixels')->nullable();
                $table->unsignedSmallInteger('page_count')->nullable();
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(false)->index();
                $table->timestamp('activated_at')->nullable()->index();
                $table->timestamp('superseded_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('certificates')) {
            Schema::create('certificates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('research_application_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('applicant_user_id')->constrained('users')->restrictOnDelete();
                $table->string('certificate_number', 80)->unique();
                $table->string('status', 30)->index();
                $table->string('generation_failure_code', 60)->nullable();
                $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('released_at')->nullable()->index();
                $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('claimed_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('certificate_versions')) {
            Schema::create('certificate_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('certificate_version');
                $table->string('status', 30)->index();
                $table->string('stored_file_path');
                $table->string('original_file_name');
                $table->string('mime_type', 120)->default('application/pdf');
                $table->unsignedBigInteger('file_size_bytes');
                $table->char('sha256', 64)->index();
                $table->string('official_template_version', 60);
                $table->char('official_template_sha256', 64);
                $table->foreignId('certificate_background_id')
                    ->nullable()
                    ->constrained('certificate_backgrounds')
                    ->restrictOnDelete();
                $table->char('background_sha256', 64);
                $table->string('generator_version', 60);
                $table->foreignId('generated_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('generated_at')->index();
                $table->foreignId('released_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('released_at')->index();
                $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('claimed_at')->nullable()->index();
                $table->timestamps();

                $table->unique(
                    ['certificate_id', 'certificate_version'],
                    'certificate_version_unique',
                );
            });
        }

        if (! Schema::hasColumn('certificates', 'current_certificate_version_id')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->foreignId('current_certificate_version_id')
                    ->nullable()
                    ->after('generation_failure_code');
            });
        }

        if (! Schema::hasForeignKey('certificates', 'certificates_current_certificate_version_id_foreign')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->foreign('current_certificate_version_id')
                    ->references('id')
                    ->on('certificate_versions')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('certificates', 'claimed_certificate_version_id')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->foreignId('claimed_certificate_version_id')
                    ->nullable()
                    ->after('claimed_by_user_id');
            });
        }

        if (! Schema::hasForeignKey('certificates', 'certificates_claimed_certificate_version_id_foreign')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->foreign('claimed_certificate_version_id')
                    ->references('id')
                    ->on('certificate_versions')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('review_comments', 'application_decision_release_id')) {
            Schema::table('review_comments', function (Blueprint $table): void {
                $table->foreignId('application_decision_release_id')
                    ->nullable()
                    ->after('resolved_by_user_id');
            });
        }

        if (! Schema::hasForeignKey('review_comments', 'review_comments_application_decision_release_id_foreign')) {
            Schema::table('review_comments', function (Blueprint $table): void {
                $table->foreign('application_decision_release_id')
                    ->references('id')
                    ->on('application_decision_releases')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('review_comments', 'released_by_user_id')) {
            Schema::table('review_comments', function (Blueprint $table): void {
                $table->foreignId('released_by_user_id')->nullable()->after('released_at');
            });
        }

        if (! Schema::hasForeignKey('review_comments', 'review_comments_released_by_user_id_foreign')) {
            Schema::table('review_comments', function (Blueprint $table): void {
                $table->foreign('released_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'review_cycle')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->unsignedSmallInteger('review_cycle')->default(0)->after('review_type');
            });
        }

        if (! Schema::hasIndex('reviewer_assignments', 'reviewer_assignment_cycle_index')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->index(
                    ['research_application_id', 'review_cycle', 'assignment_status'],
                    'reviewer_assignment_cycle_index',
                );
            });
        }

        if (! Schema::hasColumn('application_documents', 'file_sha256')) {
            Schema::table('application_documents', function (Blueprint $table): void {
                $table->char('file_sha256', 64)->nullable()->after('file_size_bytes')->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['certificate_versions', 'application_revisions', 'application_decision_releases'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException(
                    'Applicant revision or issued certificate history exists; this migration cannot be rolled back without explicit archival handling.',
                );
            }
        }

        if (Schema::hasTable('certificates')) {
            foreach ([
                'certificates_current_certificate_version_id_foreign',
                'certificates_claimed_certificate_version_id_foreign',
            ] as $foreign) {
                if (Schema::hasForeignKey('certificates', $foreign)) {
                    Schema::table('certificates', fn (Blueprint $table) => $table->dropForeign($foreign));
                }
            }
        }

        Schema::dropIfExists('certificate_versions');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_backgrounds');
        Schema::dropIfExists('applicant_survey_responses');
        Schema::dropIfExists('application_revision_requirements');
        Schema::dropIfExists('application_revisions');

        foreach ([
            'review_comments_application_decision_release_id_foreign' => 'application_decision_release_id',
            'review_comments_released_by_user_id_foreign' => 'released_by_user_id',
        ] as $foreign => $column) {
            if (Schema::hasForeignKey('review_comments', $foreign)) {
                Schema::table('review_comments', fn (Blueprint $table) => $table->dropForeign($foreign));
            }
            if (Schema::hasColumn('review_comments', $column)) {
                Schema::table('review_comments', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        Schema::dropIfExists('application_decision_releases');

        if (Schema::hasIndex('reviewer_assignments', 'reviewer_assignment_cycle_index')) {
            Schema::table('reviewer_assignments', fn (Blueprint $table) => $table->dropIndex('reviewer_assignment_cycle_index'));
        }
        if (Schema::hasColumn('reviewer_assignments', 'review_cycle')) {
            Schema::table('reviewer_assignments', fn (Blueprint $table) => $table->dropColumn('review_cycle'));
        }
        if (Schema::hasColumn('application_documents', 'file_sha256')) {
            Schema::table('application_documents', fn (Blueprint $table) => $table->dropColumn('file_sha256'));
        }
    }
};
