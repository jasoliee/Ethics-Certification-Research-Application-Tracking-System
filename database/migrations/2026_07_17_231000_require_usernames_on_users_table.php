<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('username')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                $base = 'user-'.$user->id;
                $username = $base;
                $suffix = 1;

                while (DB::table('users')->where('username', $username)->exists()) {
                    $ending = '-'.$suffix++;
                    $username = substr($base, 0, 30 - strlen($ending)).$ending;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $username]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->change();
        });
    }
};
