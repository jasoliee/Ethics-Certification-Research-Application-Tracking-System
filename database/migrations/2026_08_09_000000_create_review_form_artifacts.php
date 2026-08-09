<?php

use App\Enums\ReviewFormArtifactStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_form_submissions', function (Blueprint $table): void {
            $table->json('finalized_context_snapshot')->nullable()->after('finalized_payload_snapshot');
        });

        Schema::create('review_form_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_form_submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('artifact_version');
            $table->string('status', 20)->default(ReviewFormArtifactStatus::Ready->value)->index();
            $table->string('stored_file_path')->unique();
            $table->string('original_file_name');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('file_size_bytes');
            $table->char('sha256', 64)->index();
            $table->string('template_code', 30);
            $table->string('template_version', 64);
            $table->char('template_sha256', 64);
            $table->string('generator_version', 64);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(
                ['review_form_submission_id', 'artifact_version'],
                'review_form_artifact_version_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_form_artifacts');

        Schema::table('review_form_submissions', function (Blueprint $table): void {
            $table->dropColumn('finalized_context_snapshot');
        });
    }
};
