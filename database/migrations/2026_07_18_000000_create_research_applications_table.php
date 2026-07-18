<?php

use App\Enums\ApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('application_code', 40)->unique();
            $table->foreignId('applicant_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('adviser_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('applicant_type', 20)->default('student');
            $table->string('research_title');
            $table->string('application_type', 40)->default('new_application');
            $table->string('application_status', 60)->default(ApplicationStatus::Draft->value)->index();
            $table->string('review_type', 30)->nullable()->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamps();

            $table->index(['adviser_user_id', 'application_status']);
            $table->index(['applicant_user_id', 'application_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_applications');
    }
};
