<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Department is retired in favor of the existing Institute-backed institution field.
        DB::table('profile_options')->where('field', 'department')->delete();

        if (Schema::hasColumn('research_applications', 'department')) {
            Schema::table('research_applications', function (Blueprint $table): void {
                $table->dropColumn('department');
            });
        }

        if (Schema::hasColumn('users', 'department')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('department');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'department')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('department', 150)->nullable()->after('institution');
            });
        }

        if (! Schema::hasColumn('research_applications', 'department')) {
            Schema::table('research_applications', function (Blueprint $table): void {
                $table->string('department', 150)->nullable()->after('institution');
            });
        }
    }
};
