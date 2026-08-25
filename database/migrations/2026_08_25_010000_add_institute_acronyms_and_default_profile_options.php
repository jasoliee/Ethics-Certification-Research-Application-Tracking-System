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
        Schema::table('profile_options', function (Blueprint $table): void {
            $table->string('acronym', 12)->nullable()->after('normalized_value');
            $table->unique('acronym');
        });

        DB::transaction(function (): void {
            foreach ([
                ['First Year', '1st Year'],
                ['Second Year', '2nd Year'],
                ['Third Year', '3rd Year'],
                ['Fourth Year', '4th Year'],
            ] as $index => [$legacyValue, $value]) {
                $this->ensureRenamedYearLevel($legacyValue, $value, ($index + 1) * 10);
            }

            foreach ([
                'Institute of Behavioral Sciences' => 'IBS',
                'Institute of Computing and Digital Innovation' => 'ICDI',
                'Institute of Engineering' => 'IOE',
                'Institute of Foundational Studies' => 'IFS',
                'Institute of Governance and Development Studies' => 'IGDS',
                'Institute of Medical Laboratory Science' => 'IMLS',
                'Institute of Midwifery' => 'IOM',
                'Institute of Nursing' => 'ION',
                'Institute of Science and Mathematics' => 'ISM',
            ] as $value => $acronym) {
                $this->ensureOption('institution', $value, $acronym);
            }

            foreach ([
                'Bachelor of Science in Psychology',
                'Bachelor of Science in Computer Science',
                'Bachelor of Science in Data Science',
                'Bachelor of Science in Information Systems',
                'Bachelor of Science in Civil Engineering',
                'Bachelor of Science in Social Work',
                'Bachelor of Science in Medical Laboratory Science',
                'Bachelor of Science in Midwifery',
                'Bachelor of Science in Nursing',
                'Bachelor of Science in Life Sciences',
            ] as $index => $value) {
                $this->ensureOption('program', $value, null, ($index + 1) * 10);
            }
        });
    }

    public function down(): void
    {
        Schema::table('profile_options', function (Blueprint $table): void {
            $table->dropUnique(['acronym']);
            $table->dropColumn('acronym');
        });
    }

    private function ensureRenamedYearLevel(string $legacyValue, string $value, int $sortOrder): void
    {
        $legacyNormalized = $this->normalize($legacyValue);
        $normalized = $this->normalize($value);
        $current = DB::table('profile_options')
            ->where('field', 'year_level')
            ->where('normalized_value', $normalized)
            ->first();
        $legacy = DB::table('profile_options')
            ->where('field', 'year_level')
            ->where('normalized_value', $legacyNormalized)
            ->first();

        if ($current) {
            DB::table('profile_options')->where('id', $current->id)->update([
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);
            $this->preserveAlias((int) $current->id, 'year_level', $legacyValue, $legacyNormalized);

            if ($legacy && (int) $legacy->id !== (int) $current->id) {
                DB::table('profile_option_aliases')
                    ->where('profile_option_id', $legacy->id)
                    ->update(['profile_option_id' => $current->id, 'updated_at' => now()]);
                DB::table('profile_options')->where('id', $legacy->id)->delete();
            }

            return;
        }

        if ($legacy) {
            DB::table('profile_option_aliases')
                ->where('field', 'year_level')
                ->where('normalized_value', $normalized)
                ->delete();
            $this->preserveAlias((int) $legacy->id, 'year_level', $legacyValue, $legacyNormalized);
            DB::table('profile_options')->where('id', $legacy->id)->update([
                'value' => $value,
                'normalized_value' => $normalized,
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);

            return;
        }

        $this->ensureOption('year_level', $value, null, $sortOrder);
    }

    private function preserveAlias(int $optionId, string $field, string $value, string $normalized): void
    {
        DB::table('profile_option_aliases')->updateOrInsert(
            ['field' => $field, 'normalized_value' => $normalized],
            [
                'profile_option_id' => $optionId,
                'value' => $value,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function ensureOption(string $field, string $value, ?string $acronym = null, ?int $sortOrder = null): void
    {
        $normalized = $this->normalize($value);
        $optionId = DB::table('profile_options')
            ->where('field', $field)
            ->where('normalized_value', $normalized)
            ->value('id');

        if (! $optionId) {
            $optionId = DB::table('profile_option_aliases')
                ->where('field', $field)
                ->where('normalized_value', $normalized)
                ->value('profile_option_id');
        }

        if ($optionId) {
            $updates = ['updated_at' => now()];

            if ($acronym !== null) {
                $updates['acronym'] = $acronym;
            }

            if ($sortOrder !== null) {
                $updates['sort_order'] = $sortOrder;
            }

            DB::table('profile_options')->where('id', $optionId)->update($updates);

            return;
        }

        DB::table('profile_options')->insert([
            'field' => $field,
            'value' => $value,
            'normalized_value' => $normalized,
            'acronym' => $acronym,
            'sort_order' => $sortOrder ?? (((int) DB::table('profile_options')->where('field', $field)->max('sort_order')) + 10),
            'is_active' => true,
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::squish($value));
    }
};
