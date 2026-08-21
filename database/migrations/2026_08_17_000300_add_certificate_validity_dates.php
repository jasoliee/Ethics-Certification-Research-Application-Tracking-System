<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->date('issued_date')->nullable()->after('released_at')->index();
            $table->date('valid_until')->nullable()->after('issued_date')->index();
        });
        Schema::table('certificate_versions', function (Blueprint $table): void {
            $table->date('issued_date')->nullable()->after('generated_at')->index();
            $table->date('valid_until')->nullable()->after('issued_date')->index();
        });

        DB::table('certificate_versions')->orderBy('id')->each(function (object $version): void {
            $issued = $version->generated_at ?: $version->released_at;
            if (! $issued) {
                return;
            }

            $issuedDate = CarbonImmutable::parse($issued)->startOfDay();
            DB::table('certificate_versions')->where('id', $version->id)->update([
                'issued_date' => $issuedDate->toDateString(),
                'valid_until' => $issuedDate->addYearNoOverflow()->toDateString(),
            ]);
        });
        DB::table('certificates')->orderBy('id')->each(function (object $certificate): void {
            $current = filled($certificate->current_certificate_version_id)
                ? DB::table('certificate_versions')->where('id', $certificate->current_certificate_version_id)->first()
                : null;
            if ($current?->issued_date) {
                DB::table('certificates')->where('id', $certificate->id)->update([
                    'issued_date' => $current->issued_date,
                    'valid_until' => $current->valid_until,
                ]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('certificate_versions')->whereNotNull('issued_date')->exists()) {
            throw new RuntimeException('Issued certificate validity provenance exists and cannot be discarded safely.');
        }

        Schema::table('certificate_versions', fn (Blueprint $table) => $table->dropColumn(['issued_date', 'valid_until']));
        Schema::table('certificates', fn (Blueprint $table) => $table->dropColumn(['issued_date', 'valid_until']));
    }
};
