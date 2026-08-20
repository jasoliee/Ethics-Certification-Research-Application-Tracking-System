<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('notifications', 'deleted_at')) {
                $table->softDeletes();
                $table->index(
                    ['notifiable_type', 'notifiable_id', 'deleted_at', 'created_at'],
                    'notifications_bin_lookup_index',
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            if (Schema::hasIndex('notifications', 'notifications_bin_lookup_index')) {
                $table->dropIndex('notifications_bin_lookup_index');
            }
            if (Schema::hasColumn('notifications', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
