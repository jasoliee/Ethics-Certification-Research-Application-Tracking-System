<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may use the old composite unique index to support this foreign key.
        // Give the foreign key a dedicated index before replacing that constraint.
        Schema::table('reviewer_assignments', function (Blueprint $table): void {
            $table->index('research_application_id', 'reviewer_assignments_application_fk_index');
        });

        Schema::table('reviewer_assignments', function (Blueprint $table): void {
            $table->dropUnique('reviewer_application_type_unique');
            $table->unsignedInteger('assignment_sequence')->default(1)->after('review_type');
            $table->foreignId('replaces_assignment_id')->nullable()->after('assignment_sequence')
                ->constrained('reviewer_assignments')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable()->after('submitted_at')->index();
            $table->foreignId('superseded_by_user_id')->nullable()->after('superseded_at')
                ->constrained('users')->nullOnDelete();
            $table->text('supersession_reason')->nullable()->after('superseded_by_user_id');
            $table->string('superseded_from_status', 30)->nullable()->after('supersession_reason');
            $table->unique(
                ['research_application_id', 'reviewer_user_id', 'review_type', 'assignment_sequence'],
                'reviewer_application_type_sequence_unique',
            );
            $table->index(
                ['research_application_id', 'review_type', 'superseded_at'],
                'reviewer_assignment_current_set_index',
            );
        });

        Schema::table('reviewer_assignments', function (Blueprint $table): void {
            $table->dropIndex(['conflict_status']);
            $table->dropColumn('conflict_status');
            $table->dropColumn('conflict_cleared_at');
            $table->dropColumn('conflict_declared_at');
        });

        Schema::table('application_screenings', function (Blueprint $table): void {
            $table->dropColumn('completeness_status');
            $table->dropColumn('receipt_check_status');
            $table->dropColumn('required_documents_verified');
            $table->dropColumn('receipt_status_recorded');
            $table->dropColumn('basic_eligibility_confirmed');
            $table->dropColumn('screening_notes');
        });
    }

    public function down(): void
    {
        Schema::table('application_screenings', function (Blueprint $table): void {
            $table->string('completeness_status', 20)->nullable();
            $table->string('receipt_check_status', 20)->nullable();
            $table->boolean('required_documents_verified')->nullable();
            $table->boolean('receipt_status_recorded')->nullable();
            $table->boolean('basic_eligibility_confirmed')->nullable();
            $table->text('screening_notes')->nullable();
        });

        // Restore the old leading-column index before removing its dedicated replacement.
        Schema::table('reviewer_assignments', function (Blueprint $table): void {
            $table->unique(
                ['research_application_id', 'reviewer_user_id', 'review_type'],
                'reviewer_application_type_unique',
            );
        });

        Schema::table('reviewer_assignments', function (Blueprint $table): void {
            $table->string('conflict_status', 30)->default('pending')->index();
            $table->timestamp('conflict_cleared_at')->nullable();
            $table->timestamp('conflict_declared_at')->nullable();
            $table->dropUnique('reviewer_application_type_sequence_unique');
            $table->dropIndex('reviewer_assignment_current_set_index');
            $table->dropIndex('reviewer_assignments_application_fk_index');
            $table->dropForeign(['replaces_assignment_id']);
            $table->dropForeign(['superseded_by_user_id']);
            $table->dropColumn([
                'assignment_sequence',
                'replaces_assignment_id',
                'superseded_at',
                'superseded_by_user_id',
                'supersession_reason',
                'superseded_from_status',
            ]);
        });
    }
};
