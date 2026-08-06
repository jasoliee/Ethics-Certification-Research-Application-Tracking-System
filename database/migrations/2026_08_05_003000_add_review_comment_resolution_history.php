<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_comments', function (Blueprint $table): void {
            $table->string('status', 20)->default('open')->after('body')->index();
            $table->timestamp('resolved_at')->nullable()->after('status');
            $table->foreignId('resolved_by_user_id')->nullable()->after('resolved_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('review_comment_status_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['review_comment_id', 'changed_at'], 'review_comment_status_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_comment_status_changes');
        Schema::table('review_comments', function (Blueprint $table): void {
            $table->dropForeign(['resolved_by_user_id']);
            $table->dropIndex(['status']);
            $table->dropColumn('status');
            $table->dropColumn('resolved_at');
            $table->dropColumn('resolved_by_user_id');
        });
    }
};
