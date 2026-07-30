<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('semester', 50);
            $table->string('academic_year', 20);
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['semester', 'academic_year']);
            $table->index(['is_active', 'starts_at', 'ends_at'], 'academic_terms_active_timeframe_index');
        });

        Schema::table('deadline_configurations', function (Blueprint $table): void {
            $table->foreignId('academic_term_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('timeline_calendar_events', function (Blueprint $table): void {
            $table->foreignId('academic_term_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('research_applications', function (Blueprint $table): void {
            $table->foreignId('academic_term_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('academic_term_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_term_id');
        });

        Schema::table('research_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_term_id');
        });

        Schema::table('timeline_calendar_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_term_id');
        });

        Schema::table('deadline_configurations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_term_id');
        });

        Schema::dropIfExists('academic_terms');
    }
};
