<?php

use App\Enums\ReviewerConflictStatus;
use App\Enums\ReviewFormStatus;
use App\Enums\ReviewSubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviewer_assignments', function (Blueprint $table): void {
            $table->string('conflict_status', 30)
                ->default(ReviewerConflictStatus::Pending->value)
                ->after('assignment_status')
                ->index();
            $table->timestamp('conflict_cleared_at')->nullable()->after('conflict_status');
            $table->timestamp('conflict_declared_at')->nullable()->after('conflict_cleared_at');
        });

        Schema::create('review_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reviewer_assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default(ReviewSubmissionStatus::Draft->value)->index();
            $table->string('decision', 30)->nullable();
            $table->text('decision_comment')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('review_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reviewer_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('form_type', 30);
            $table->string('status', 20)->default(ReviewFormStatus::Draft->value)->index();
            $table->json('responses')->nullable();
            $table->boolean('consent_required')->nullable();
            $table->text('consent_not_required_explanation')->nullable();
            $table->string('recommendation', 30)->nullable();
            $table->text('recommendation_comments')->nullable();
            $table->date('review_date')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['reviewer_assignment_id', 'form_type'], 'reviewer_assignment_form_unique');
        });

        Schema::create('review_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reviewer_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 20)->index();
            $table->string('category', 30);
            $table->unsignedInteger('page_number')->nullable();
            $table->text('body');
            $table->timestamp('released_at')->nullable()->index();
            $table->timestamps();

            $table->index(['reviewer_assignment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_comments');
        Schema::dropIfExists('review_form_submissions');
        Schema::dropIfExists('review_submissions');

        Schema::table('reviewer_assignments', function (Blueprint $table): void {
            $table->dropColumn([
                'conflict_status',
                'conflict_cleared_at',
                'conflict_declared_at',
            ]);
        });
    }
};
