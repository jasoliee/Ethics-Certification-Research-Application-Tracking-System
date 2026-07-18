<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deadline_configurations', function (Blueprint $table): void {
            $table->id();
            $table->string('deadline_key', 80)->unique();
            $table->string('title');
            $table->string('audience_role', 50)->nullable()->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('due_at')->index();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('timeline_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->string('milestone_key', 80)->unique();
            $table->string('label');
            $table->string('term_label')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_calendar_events');
        Schema::dropIfExists('deadline_configurations');
    }
};
