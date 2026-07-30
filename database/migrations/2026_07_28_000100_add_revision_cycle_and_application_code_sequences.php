<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_code_sequences', function (Blueprint $table): void {
            $table->string('period', 7)->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        Schema::table('research_applications', function (Blueprint $table): void {
            $table->unsignedSmallInteger('current_revision_cycle')
                ->default(1)
                ->after('review_type');
        });
    }

    public function down(): void
    {
        Schema::table('research_applications', function (Blueprint $table): void {
            $table->dropColumn('current_revision_cycle');
        });

        Schema::dropIfExists('application_code_sequences');
    }
};
