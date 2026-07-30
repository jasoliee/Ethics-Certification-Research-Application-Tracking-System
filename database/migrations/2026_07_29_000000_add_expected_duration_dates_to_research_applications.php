<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add structured research dates without discarding historical free-text durations.
     */
    public function up(): void
    {
        Schema::table('research_applications', function (Blueprint $table): void {
            $table->date('expected_start_date')->nullable()->after('expected_duration');
            $table->date('expected_end_date')->nullable()->after('expected_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('research_applications', function (Blueprint $table): void {
            $table->dropColumn(['expected_start_date', 'expected_end_date']);
        });
    }
};
