<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviewer_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('declared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('declared_at');
            $table->foreignId('cleared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['research_application_id', 'reviewer_user_id'],
                'reviewer_conflicts_application_reviewer_unique',
            );
            $table->index(
                ['reviewer_user_id', 'cleared_at'],
                'reviewer_conflicts_reviewer_active_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_conflicts');
    }
};
