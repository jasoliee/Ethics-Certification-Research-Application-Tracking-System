<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_form_artifacts', function (Blueprint $table): void {
            $table->foreignId('certificate_background_id')
                ->nullable()
                ->after('review_submission_version_id')
                ->constrained('certificate_backgrounds')
                ->restrictOnDelete();
            $table->char('background_sha256', 64)->nullable()->after('certificate_background_id');
        });
    }

    public function down(): void
    {
        if (DB::table('review_form_artifacts')->whereNotNull('certificate_background_id')->exists()) {
            throw new \RuntimeException('Review worksheet background provenance exists and cannot be discarded safely.');
        }

        Schema::table('review_form_artifacts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('certificate_background_id');
            $table->dropColumn('background_sha256');
        });
    }
};
