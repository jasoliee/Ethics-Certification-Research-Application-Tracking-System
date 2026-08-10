<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL commits DDL statements individually. Guard every step so a migration
        // interrupted midway can resume without duplicate indexes or columns.
        // MySQL may use the old composite unique index to support this foreign key.
        // Give the foreign key a dedicated index before replacing that constraint.
        if (! Schema::hasIndex('reviewer_assignments', 'reviewer_assignments_application_fk_index')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->index('research_application_id', 'reviewer_assignments_application_fk_index');
            });
        }

        if (Schema::hasIndex('reviewer_assignments', 'reviewer_application_type_unique')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->dropUnique('reviewer_application_type_unique');
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'assignment_sequence')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->unsignedInteger('assignment_sequence')->default(1)->after('review_type');
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'replaces_assignment_id')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->foreignId('replaces_assignment_id')->nullable()->after('assignment_sequence');
            });
        }

        if (! Schema::hasForeignKey('reviewer_assignments', 'reviewer_assignments_replaces_assignment_id_foreign')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->foreign('replaces_assignment_id')
                    ->references('id')
                    ->on('reviewer_assignments')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'superseded_at')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->timestamp('superseded_at')->nullable()->after('submitted_at');
            });
        }

        if (! Schema::hasIndex('reviewer_assignments', 'reviewer_assignments_superseded_at_index')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->index('superseded_at');
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'superseded_by_user_id')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->foreignId('superseded_by_user_id')->nullable()->after('superseded_at');
            });
        }

        if (! Schema::hasForeignKey('reviewer_assignments', 'reviewer_assignments_superseded_by_user_id_foreign')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->foreign('superseded_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'supersession_reason')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->text('supersession_reason')->nullable()->after('superseded_by_user_id');
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'superseded_from_status')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->string('superseded_from_status', 30)->nullable()->after('supersession_reason');
            });
        }

        if (! Schema::hasIndex('reviewer_assignments', 'reviewer_application_type_sequence_unique')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->unique(
                    ['research_application_id', 'reviewer_user_id', 'review_type', 'assignment_sequence'],
                    'reviewer_application_type_sequence_unique',
                );
            });
        }

        if (! Schema::hasIndex('reviewer_assignments', 'reviewer_assignment_current_set_index')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->index(
                    ['research_application_id', 'review_type', 'superseded_at'],
                    'reviewer_assignment_current_set_index',
                );
            });
        }

        if (Schema::hasIndex('reviewer_assignments', 'reviewer_assignments_conflict_status_index')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->dropIndex('reviewer_assignments_conflict_status_index');
            });
        }

        foreach (['conflict_status', 'conflict_cleared_at', 'conflict_declared_at'] as $column) {
            if (Schema::hasColumn('reviewer_assignments', $column)) {
                Schema::table('reviewer_assignments', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        foreach ([
            'completeness_status',
            'receipt_check_status',
            'required_documents_verified',
            'receipt_status_recorded',
            'basic_eligibility_confirmed',
            'screening_notes',
        ] as $column) {
            if (Schema::hasColumn('application_screenings', $column)) {
                Schema::table('application_screenings', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        $hasRepeatedAssignments = Schema::hasColumn('reviewer_assignments', 'assignment_sequence')
            && DB::table('reviewer_assignments')
                ->select(['research_application_id', 'reviewer_user_id', 'review_type'])
                ->groupBy(['research_application_id', 'reviewer_user_id', 'review_type'])
                ->havingRaw('COUNT(*) > 1')
                ->exists();

        if ($hasRepeatedAssignments) {
            throw new RuntimeException(
                'This migration cannot be rolled back after reviewer assignment history exists because doing so would discard preserved records.',
            );
        }

        if (! Schema::hasColumn('application_screenings', 'completeness_status')) {
            Schema::table('application_screenings', function (Blueprint $table): void {
                $table->string('completeness_status', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('application_screenings', 'receipt_check_status')) {
            Schema::table('application_screenings', function (Blueprint $table): void {
                $table->string('receipt_check_status', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('application_screenings', 'required_documents_verified')) {
            Schema::table('application_screenings', function (Blueprint $table): void {
                $table->boolean('required_documents_verified')->nullable();
            });
        }

        if (! Schema::hasColumn('application_screenings', 'receipt_status_recorded')) {
            Schema::table('application_screenings', function (Blueprint $table): void {
                $table->boolean('receipt_status_recorded')->nullable();
            });
        }

        if (! Schema::hasColumn('application_screenings', 'basic_eligibility_confirmed')) {
            Schema::table('application_screenings', function (Blueprint $table): void {
                $table->boolean('basic_eligibility_confirmed')->nullable();
            });
        }

        if (! Schema::hasColumn('application_screenings', 'screening_notes')) {
            Schema::table('application_screenings', function (Blueprint $table): void {
                $table->text('screening_notes')->nullable();
            });
        }

        // Restore the old leading-column index before removing its dedicated replacement.
        if (! Schema::hasIndex('reviewer_assignments', 'reviewer_application_type_unique')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->unique(
                    ['research_application_id', 'reviewer_user_id', 'review_type'],
                    'reviewer_application_type_unique',
                );
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'conflict_status')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->string('conflict_status', 30)->default('pending');
            });
        }

        if (! Schema::hasIndex('reviewer_assignments', 'reviewer_assignments_conflict_status_index')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->index('conflict_status');
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'conflict_cleared_at')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->timestamp('conflict_cleared_at')->nullable();
            });
        }

        if (! Schema::hasColumn('reviewer_assignments', 'conflict_declared_at')) {
            Schema::table('reviewer_assignments', function (Blueprint $table): void {
                $table->timestamp('conflict_declared_at')->nullable();
            });
        }

        foreach ([
            'reviewer_application_type_sequence_unique' => 'unique',
            'reviewer_assignment_current_set_index' => 'index',
            'reviewer_assignments_application_fk_index' => 'index',
        ] as $index => $type) {
            if (Schema::hasIndex('reviewer_assignments', $index)) {
                Schema::table('reviewer_assignments', function (Blueprint $table) use ($index, $type): void {
                    $type === 'unique' ? $table->dropUnique($index) : $table->dropIndex($index);
                });
            }
        }

        foreach ([
            'reviewer_assignments_replaces_assignment_id_foreign' => 'replaces_assignment_id',
            'reviewer_assignments_superseded_by_user_id_foreign' => 'superseded_by_user_id',
        ] as $foreign => $column) {
            if (Schema::hasForeignKey('reviewer_assignments', $foreign)) {
                Schema::table('reviewer_assignments', function (Blueprint $table) use ($foreign): void {
                    $table->dropForeign($foreign);
                });
            }

            if (Schema::hasColumn('reviewer_assignments', $column)) {
                Schema::table('reviewer_assignments', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        foreach (['assignment_sequence', 'superseded_at', 'supersession_reason', 'superseded_from_status'] as $column) {
            if (Schema::hasColumn('reviewer_assignments', $column)) {
                Schema::table('reviewer_assignments', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
