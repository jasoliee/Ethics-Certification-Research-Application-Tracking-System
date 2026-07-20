<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add normalized identity and administrative profile fields without replacing Laravel's auth record.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('suffix', 30)->nullable()->after('last_name');
            $table->string('institutional_identifier', 50)->nullable()->unique()->after('email');
            $table->string('phone_number', 30)->nullable()->after('institutional_identifier');
            $table->string('institution', 150)->nullable()->after('phone_number');
            $table->string('department', 150)->nullable()->after('institution');
            $table->string('position_title', 150)->nullable()->after('department');
            $table->foreignId('created_by_user_id')->nullable()->after('account_status')->constrained('users')->nullOnDelete();
            $table->timestamp('password_changed_at')->nullable()->after('remember_token');
            $table->index(['role', 'account_status']);
        });

        // Existing records receive usable compatibility values that administrators can review later.
        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->eachById(function (object $user): void {
                $parts = preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $firstName = array_shift($parts) ?: 'Existing';
                $lastName = implode(' ', $parts) ?: 'User';

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'institutional_identifier' => 'LEGACY-'.$user->id,
                    ]);
            });

        // New account requirements are also protected by database-level null constraints.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable(false)->change();
            $table->string('last_name', 100)->nullable(false)->change();
            $table->string('institutional_identifier', 50)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role', 'account_status']);
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropUnique(['institutional_identifier']);
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'institutional_identifier',
                'phone_number',
                'institution',
                'department',
                'position_title',
                'password_changed_at',
            ]);
        });
    }
};
