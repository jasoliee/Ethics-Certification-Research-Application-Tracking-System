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
            $table->string('program', 150)->nullable()->after('department');
            $table->string('year_level', 30)->nullable()->after('program');
            $table->string('reviewer_classification', 30)->nullable()->after('position_title');
            $table->unsignedSmallInteger('reviewer_capacity')->nullable()->after('reviewer_classification');
            $table->timestamp('password_setup_completed_at')->nullable()->after('password_changed_at');
            $table->timestamp('onboarding_completed_at')->nullable()->after('password_setup_completed_at');
            $table->string('setup_email_status', 20)->default('not_sent')->after('onboarding_completed_at');
            $table->timestamp('setup_email_sent_at')->nullable()->after('setup_email_status');
            $table->timestamp('setup_email_failed_at')->nullable()->after('setup_email_sent_at');
            $table->softDeletes();

            $table->index(['account_status', 'password_setup_completed_at']);
        });

        // Existing accounts already have usable credentials and must not enter new-account onboarding.
        DB::table('users')->update([
            'password_setup_completed_at' => now(),
            'onboarding_completed_at' => now(),
            'setup_email_status' => 'not_required',
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['account_status', 'password_setup_completed_at']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'program',
                'year_level',
                'reviewer_classification',
                'reviewer_capacity',
                'password_setup_completed_at',
                'onboarding_completed_at',
                'setup_email_status',
                'setup_email_sent_at',
                'setup_email_failed_at',
            ]);
        });
    }
};
