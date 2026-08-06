<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('deadline_configurations')
            ->where(function ($query): void {
                $query->where('deadline_key', 'result-release')
                    ->orWhere('deadline_key', 'like', '%-result-release');
            })
            ->update(['is_active' => false]);

        DB::table('timeline_calendar_events')
            ->where('label', 'Release of Decision & Certificate')
            ->where(function ($query): void {
                $query->where('milestone_key', 'release')
                    ->orWhere('milestone_key', 'like', '%-release');
            })
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('deadline_configurations')
            ->where(function ($query): void {
                $query->where('deadline_key', 'result-release')
                    ->orWhere('deadline_key', 'like', '%-result-release');
            })
            ->update(['is_active' => true]);

        DB::table('timeline_calendar_events')
            ->where('label', 'Release of Decision & Certificate')
            ->where(function ($query): void {
                $query->where('milestone_key', 'release')
                    ->orWhere('milestone_key', 'like', '%-release');
            })
            ->update(['is_active' => true]);
    }
};
