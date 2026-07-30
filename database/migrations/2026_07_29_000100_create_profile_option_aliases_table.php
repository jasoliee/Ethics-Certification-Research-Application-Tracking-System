<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve prior visible labels against one immutable profile-option identity.
     */
    public function up(): void
    {
        Schema::create('profile_option_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_option_id')->constrained()->cascadeOnDelete();
            $table->string('field', 40);
            $table->string('value', 150);
            $table->string('normalized_value', 150);
            $table->timestamps();

            $table->unique(['field', 'normalized_value']);
            $table->index(['profile_option_id', 'normalized_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_option_aliases');
    }
};
