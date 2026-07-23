<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_options', function (Blueprint $table): void {
            $table->id();
            $table->string('field', 40);
            $table->string('value', 150);
            $table->string('normalized_value', 150);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['field', 'normalized_value']);
            $table->index(['field', 'is_active', 'sort_order']);
        });

        $defaults = [
            'year_level' => [
                'First Year',
                'Second Year',
                'Third Year',
                'Fourth Year',
            ],
            'institution' => [
                'Institute of Behavioral Sciences',
                'Institute of Computing and Digital Innovation',
                'Institute of Engineering',
                'Institute of Foundational Studies',
                'Institute of Governance and Development Studies',
                'Institute of Medical Laboratory Science',
                'Institute of Midwifery',
                'Institute of Nursing',
                'Institute of Science and Mathematics',
            ],
        ];
        $now = now();
        $rows = [];

        foreach ($defaults as $field => $values) {
            foreach ($values as $index => $value) {
                $rows[] = [
                    'field' => $field,
                    'value' => $value,
                    'normalized_value' => Str::lower($value),
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('profile_options')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_options');
    }
};
