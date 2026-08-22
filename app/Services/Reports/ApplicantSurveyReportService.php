<?php

namespace App\Services\Reports;

use App\Models\ApplicantSurveyResponse;
use App\Support\ApplicantSurveyCatalog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Produces anonymous aggregates without loading applicant identities or comments.
 */
class ApplicantSurveyReportService
{
    /**
     * @return array{
     *     response_count: int,
     *     legacy_response_count: int,
     *     overall_average: float|null,
     *     sections: array<string, array{title: string, average: float|null, questions: array<string, array{label: string, average: float|null, response_count: int}>}>
     * }
     */
    /** @param array<string, mixed> $filters */
    public function summary(array $filters = []): array
    {
        $questionSums = array_fill_keys(ApplicantSurveyCatalog::questionKeys(), 0);
        $questionCounts = array_fill_keys(ApplicantSurveyCatalog::questionKeys(), 0);
        $responseCount = 0;

        ApplicantSurveyResponse::query()
            ->select(['id', 'ratings'])
            ->where('questionnaire_version', ApplicantSurveyCatalog::VERSION)
            ->whereHas('researchApplication', fn (Builder $applications) => $this->applyApplicationFilters($applications, $filters))
            ->lazyById(500)
            ->each(function (ApplicantSurveyResponse $response) use (&$questionSums, &$questionCounts, &$responseCount): void {
                $ratings = is_array($response->ratings) ? $response->ratings : [];
                $normalizedRatings = [];

                foreach (ApplicantSurveyCatalog::questionKeys() as $key) {
                    $rating = filter_var($ratings[$key] ?? null, FILTER_VALIDATE_INT);
                    if ($rating === false || $rating < 1 || $rating > 5) {
                        return;
                    }

                    $normalizedRatings[$key] = $rating;
                }

                foreach ($normalizedRatings as $key => $rating) {
                    $questionSums[$key] += $rating;
                    $questionCounts[$key]++;
                }

                $responseCount++;
            });

        $sections = [];
        $overallSum = 0;
        $overallCount = 0;

        foreach (ApplicantSurveyCatalog::sections() as $sectionKey => $section) {
            $questionSummaries = [];
            $sectionSum = 0;
            $sectionCount = 0;

            foreach ($section['questions'] as $key => $label) {
                $sum = $questionSums[$key];
                $count = $questionCounts[$key];
                $questionSummaries[$key] = [
                    'label' => $label,
                    'average' => $count > 0 ? round($sum / $count, 2) : null,
                    'response_count' => $count,
                ];
                $sectionSum += $sum;
                $sectionCount += $count;
            }

            $sections[$sectionKey] = [
                'title' => $section['title'],
                'average' => $sectionCount > 0 ? round($sectionSum / $sectionCount, 2) : null,
                'questions' => $questionSummaries,
            ];
            $overallSum += $sectionSum;
            $overallCount += $sectionCount;
        }

        return [
            'response_count' => $responseCount,
            'legacy_response_count' => ApplicantSurveyResponse::query()
                ->where('questionnaire_version', '<>', ApplicantSurveyCatalog::VERSION)
                ->whereHas('researchApplication', fn (Builder $applications) => $this->applyApplicationFilters($applications, $filters))
                ->count(),
            'overall_average' => $overallCount > 0 ? round($overallSum / $overallCount, 2) : null,
            'sections' => $sections,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function applyApplicationFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['academic_term_id'] ?? null), fn (Builder $applications) => $applications
                ->where('academic_term_id', (int) $filters['academic_term_id']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $applications) => $applications
                ->whereDate('submitted_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $applications) => $applications
                ->whereDate('submitted_at', '<=', $filters['date_to']))
            ->when(filled($filters['research_type'] ?? null), fn (Builder $applications) => $applications
                ->where('research_type', $filters['research_type']))
            ->when(filled($filters['applicant_type'] ?? null), fn (Builder $applications) => $applications
                ->where('applicant_type', $filters['applicant_type']))
            ->when(filled($filters['review_type'] ?? null), fn (Builder $applications) => $applications
                ->where('review_type', $filters['review_type']))
            ->when(filled($filters['department'] ?? null), fn (Builder $applications) => $applications
                ->where('department', $filters['department']))
            ->when(filled($filters['institution'] ?? null), fn (Builder $applications) => $applications
                ->where('institution', $filters['institution']))
            ->when(filled($filters['application_status'] ?? null), fn (Builder $applications) => $applications
                ->where('application_status', $filters['application_status']));
    }
}
