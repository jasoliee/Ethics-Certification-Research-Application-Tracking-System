<?php

use App\Enums\RequirementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requirements', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('application_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_requirement_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('original_file_name');
            $table->string('stored_file_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedSmallInteger('document_version')->default(1);
            $table->string('validation_status', 25)->default(RequirementStatus::Pending->value)->index();
            $table->boolean('is_current')->default(true)->index();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['research_application_id', 'document_requirement_id', 'is_current'], 'application_requirement_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('document_requirements');
    }
};
