<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_UNIQUE = 'certificate_backgrounds_asset_version_unique';

    private const TYPE_VERSION_UNIQUE = 'certificate_background_type_version_unique';

    public function up(): void
    {
        Schema::table('certificate_backgrounds', function (Blueprint $table): void {
            if (Schema::hasIndex('certificate_backgrounds', self::LEGACY_UNIQUE)) {
                $table->dropUnique(self::LEGACY_UNIQUE);
            }

            if (! Schema::hasIndex('certificate_backgrounds', self::TYPE_VERSION_UNIQUE)) {
                $table->unique(['background_type', 'asset_version'], self::TYPE_VERSION_UNIQUE);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('certificate_backgrounds')
            ->select('asset_version')
            ->groupBy('asset_version')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException(
                'Background versions overlap across types; the legacy global asset-version constraint cannot be restored safely.',
            );
        }

        Schema::table('certificate_backgrounds', function (Blueprint $table): void {
            if (Schema::hasIndex('certificate_backgrounds', self::TYPE_VERSION_UNIQUE)) {
                $table->dropUnique(self::TYPE_VERSION_UNIQUE);
            }

            if (! Schema::hasIndex('certificate_backgrounds', self::LEGACY_UNIQUE)) {
                $table->unique('asset_version', self::LEGACY_UNIQUE);
            }
        });
    }
};
