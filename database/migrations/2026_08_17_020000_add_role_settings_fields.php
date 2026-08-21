<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('expected_endorsement_count')->nullable()->after('position_title');
            $table->string('certificate_signatory_name', 120)->nullable()->after('expected_endorsement_count');
            $table->string('certificate_signature_path')->nullable()->after('certificate_signatory_name');
            $table->char('certificate_signature_sha256', 64)->nullable()->after('certificate_signature_path');
            $table->unsignedInteger('certificate_signature_width')->nullable()->after('certificate_signature_sha256');
            $table->unsignedInteger('certificate_signature_height')->nullable()->after('certificate_signature_width');
            $table->timestamp('certificate_signature_uploaded_at')->nullable()->after('certificate_signature_height');
        });

        Schema::table('certificate_backgrounds', function (Blueprint $table): void {
            $table->string('background_type', 40)->default('certificate')->after('id');
            $table->index(['background_type', 'is_active'], 'certificate_background_type_active_index');
        });
    }

    public function down(): void
    {
        $hasRoleSettings = DB::table('users')->where(function ($query): void {
            $query->whereNotNull('expected_endorsement_count')
                ->orWhereNotNull('certificate_signatory_name')
                ->orWhereNotNull('certificate_signature_path');
        })->exists();
        $hasWorksheetBackgrounds = DB::table('certificate_backgrounds')
            ->where('background_type', '!=', 'certificate')
            ->exists();

        if ($hasRoleSettings || $hasWorksheetBackgrounds) {
            throw new RuntimeException('Role settings or worksheet background provenance exists and cannot be discarded safely.');
        }

        Schema::table('certificate_backgrounds', function (Blueprint $table): void {
            $table->dropIndex('certificate_background_type_active_index');
            $table->dropColumn('background_type');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'expected_endorsement_count',
                'certificate_signatory_name',
                'certificate_signature_path',
                'certificate_signature_sha256',
                'certificate_signature_width',
                'certificate_signature_height',
                'certificate_signature_uploaded_at',
            ]);
        });
    }
};
