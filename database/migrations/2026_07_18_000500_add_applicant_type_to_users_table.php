<?php

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('applicant_type', 20)->nullable()->after('role');
        });

        // Existing applicant accounts predate the category field and receive the conservative default.
        DB::table('users')
            ->where('role', UserRole::Applicant->value)
            ->update(['applicant_type' => ApplicantType::Student->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('applicant_type');
        });
    }
};
