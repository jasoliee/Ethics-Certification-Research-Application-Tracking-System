<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist one immutable initial RES classification per application.
     */
    public function up(): void
    {
        Schema::create('application_screenings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('screened_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('completeness_status', 20);
            $table->string('receipt_check_status', 20);
            $table->boolean('required_documents_verified');
            $table->boolean('receipt_status_recorded');
            $table->boolean('basic_eligibility_confirmed');
            $table->text('screening_notes')->nullable();
            $table->string('review_type', 30)->index();
            $table->text('classification_reason');
            $table->timestamp('classified_at')->index();
            $table->timestamps();

            $table->index(['screened_by_user_id', 'classified_at']);
        });
    }

    /**
     * Remove only the additive screening records and constraints.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_screenings');
    }
};
