<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endorsements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adviser_user_id')->constrained('users')->restrictOnDelete();
            $table->string('endorsement_status', 20)->index();
            $table->string('return_reason', 80)->nullable();
            $table->text('endorsement_remarks')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('endorsed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['research_application_id', 'created_at'],
                'endorsements_application_history_index',
            );
            $table->index(
                ['adviser_user_id', 'endorsement_status'],
                'endorsements_adviser_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('endorsements');
    }
};
