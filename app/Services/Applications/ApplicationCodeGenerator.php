<?php

namespace App\Services\Applications;

use App\Enums\ApplicantType;
use App\Models\ResearchApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Allocates collision-checked public application codes without exposing database identifiers.
 */
class ApplicationCodeGenerator
{
    /** @var array<string, string> */
    private const INSTITUTION_ACRONYMS = [
        'institute of behavioral sciences' => 'IBS',
        'institute of computing and digital innovation' => 'ICDI',
        'institute of engineering' => 'IOE',
        'institute of foundational studies' => 'IFS',
        'institute of governance and development studies' => 'IGDS',
        'institute of medical laboratory science' => 'IMLS',
        'institute of midwifery' => 'IOM',
        'institute of nursing' => 'ION',
        'institute of science and mathematics' => 'ISM',
    ];

    public function next(ApplicantType $applicantType, string $institution): string
    {
        $institutionAcronym = self::INSTITUTION_ACRONYMS[Str::lower(trim($institution))] ?? null;

        if (! $institutionAcronym) {
            throw ValidationException::withMessages([
                'institution' => 'The selected institution does not have an approved application ID acronym.',
            ]);
        }

        return DB::transaction(function () use ($applicantType, $institutionAcronym): string {
            $now = now();
            $period = $now->format('Y-m');

            // The monthly row serializes allocation; its internal counter is never exposed in the public code.
            DB::table('application_code_sequences')->insertOrIgnore([
                'period' => $period,
                'last_sequence' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequenceRow = DB::table('application_code_sequences')
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequenceRow) {
                throw new RuntimeException('Unable to allocate an application code sequence.');
            }

            DB::table('application_code_sequences')
                ->where('period', $period)
                ->update([
                    'last_sequence' => ((int) $sequenceRow->last_sequence) + 1,
                    'updated_at' => $now,
                ]);

            $typeCode = match ($applicantType) {
                ApplicantType::Student => 'S',
                ApplicantType::Faculty => 'F',
            };
            $prefix = "RES-{$now->format('Y')}-{$typeCode}-{$institutionAcronym}-{$now->format('mdY')}-";

            for ($attempt = 0; $attempt < 20; $attempt++) {
                $code = $prefix.$this->randomSuffix();

                if (! ResearchApplication::query()->where('application_code', $code)->exists()) {
                    return $code;
                }
            }

            throw new RuntimeException('Unable to generate a unique application code.');
        }, 3);
    }

    /**
     * Guarantee that the six-character suffix contains both letters and numbers.
     */
    private function randomSuffix(): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $numbers = '0123456789';
        $pool = $letters.$numbers;
        $characters = [
            $letters[random_int(0, strlen($letters) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
        ];

        while (count($characters) < 6) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swapIndex = random_int(0, $index);
            [$characters[$index], $characters[$swapIndex]] = [$characters[$swapIndex], $characters[$index]];
        }

        return implode('', $characters);
    }
}
