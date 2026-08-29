<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('academic_terms', 'status')) {
            Schema::table('academic_terms', function (Blueprint $table): void {
                $table->string('status', 20)->default('active')->after('is_active');
                $table->index('status', 'academic_terms_status_index');
            });
        }

        DB::table('academic_terms')
            ->where('is_active', false)
            ->update(['status' => 'ended']);
        DB::table('academic_terms')
            ->where('is_active', true)
            ->whereNotIn('status', ['active', 'paused'])
            ->update(['status' => 'active']);

        // Update only the seeded/default REU account labels; custom personal names are preserved.
        DB::table('users')
            ->where('role', 'res_lead')
            ->where('name', 'RES Lead')
            ->update(['name' => 'REU Lead']);
        DB::table('users')
            ->where('role', 'res_lead')
            ->where('first_name', 'RES')
            ->update(['first_name' => 'REU']);
        DB::table('users')
            ->where('role', 'res_lead')
            ->where('position_title', 'RES Lead')
            ->update(['position_title' => 'REU Lead']);
        DB::table('users')
            ->where('role', 'adviser')
            ->where('reviewer_enabled', true)
            ->update(['reviewer_capacity' => 30]);
    }

    public function down(): void
    {
        throw new RuntimeException('This forward-only lifecycle migration must not be rolled back because term state would be lost.');
    }
};
