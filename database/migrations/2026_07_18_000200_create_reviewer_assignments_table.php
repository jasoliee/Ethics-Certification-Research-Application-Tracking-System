<?php

use App\Enums\ReviewerAssignmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviewer_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('review_type', 30)->default('initial_review');
            $table->string('assignment_status', 30)->default(ReviewerAssignmentStatus::Pending->value)->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('review_deadline_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['reviewer_user_id', 'assignment_status']);
            $table->unique(['research_application_id', 'reviewer_user_id', 'review_type'], 'reviewer_application_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_assignments');
    }
};
