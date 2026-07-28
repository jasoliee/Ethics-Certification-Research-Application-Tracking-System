<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deadline_configurations', function (Blueprint $table): void {
            $table->string('semester_label', 100)->nullable()->after('audience_role');
            $table->string('manual_status', 10)->nullable()->after('due_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('deadline_configurations', function (Blueprint $table): void {
            $table->dropIndex(['manual_status']);
            $table->dropColumn(['semester_label', 'manual_status']);
        });
    }
};
