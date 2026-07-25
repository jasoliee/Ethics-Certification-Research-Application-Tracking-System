<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('year_level', 150)->nullable()->change();
            $table->string('reviewer_classification', 150)->nullable()->change();
        });

        $now = now();
        $values = ['Expedited', 'Full Board', 'Exempted'];

        foreach ($values as $index => $value) {
            DB::table('profile_options')->insertOrIgnore([
                'field' => 'reviewer_classification',
                'value' => $value,
                'normalized_value' => Str::lower($value),
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'created_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')->where('reviewer_classification', 'expedited')->update(['reviewer_classification' => 'Expedited']);
        DB::table('users')->where('reviewer_classification', 'full_board')->update(['reviewer_classification' => 'Full Board']);
        DB::table('users')->where('reviewer_classification', 'exempted')->update(['reviewer_classification' => 'Exempted']);
    }

    public function down(): void
    {
        DB::table('users')->where('reviewer_classification', 'Expedited')->update(['reviewer_classification' => 'expedited']);
        DB::table('users')->where('reviewer_classification', 'Full Board')->update(['reviewer_classification' => 'full_board']);
        DB::table('users')->where('reviewer_classification', 'Exempted')->update(['reviewer_classification' => 'exempted']);

        DB::table('profile_options')
            ->where('field', 'reviewer_classification')
            ->whereNull('created_by_user_id')
            ->whereIn('normalized_value', ['expedited', 'full board', 'exempted'])
            ->delete();

        Schema::table('users', function (Blueprint $table): void {
            $table->string('year_level', 30)->nullable()->change();
            $table->string('reviewer_classification', 30)->nullable()->change();
        });
    }
};
