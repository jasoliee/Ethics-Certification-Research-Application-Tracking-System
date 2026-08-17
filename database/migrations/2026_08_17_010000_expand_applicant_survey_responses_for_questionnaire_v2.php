<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applicant_survey_responses')) {
            return;
        }

        if (! Schema::hasColumn('applicant_survey_responses', 'questionnaire_version')) {
            Schema::table('applicant_survey_responses', function (Blueprint $table): void {
                // Existing four-rating responses remain version 1; all new submissions use version 2.
                $table->unsignedTinyInteger('questionnaire_version')->default(1)->index();
            });
        }

        if (! Schema::hasColumn('applicant_survey_responses', 'suggestions_comments')) {
            Schema::table('applicant_survey_responses', function (Blueprint $table): void {
                // Retain the legacy feedback columns so historical records are never rewritten or lost.
                $table->text('suggestions_comments')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('applicant_survey_responses')) {
            return;
        }

        $columns = array_values(array_filter(
            ['questionnaire_version', 'suggestions_comments'],
            fn (string $column): bool => Schema::hasColumn('applicant_survey_responses', $column),
        ));

        if ($columns !== []) {
            Schema::table('applicant_survey_responses', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
