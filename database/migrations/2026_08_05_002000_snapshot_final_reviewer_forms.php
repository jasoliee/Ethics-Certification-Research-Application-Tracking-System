<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_form_submissions', function (Blueprint $table): void {
            $table->string('catalog_version', 30)->nullable()->after('status');
            $table->json('catalog_snapshot')->nullable()->after('catalog_version');
            $table->json('finalized_payload_snapshot')->nullable()->after('catalog_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('review_form_submissions', function (Blueprint $table): void {
            $table->dropColumn('catalog_version');
            $table->dropColumn('catalog_snapshot');
            $table->dropColumn('finalized_payload_snapshot');
        });
    }
};
