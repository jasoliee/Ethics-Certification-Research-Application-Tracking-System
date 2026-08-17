<?php

namespace App\Support;

/**
 * Stable, versioned definition for the certificate-claim feedback questionnaire.
 */
final class ApplicantSurveyCatalog
{
    public const VERSION = 2;

    /** @return array<string, string> */
    public static function ratingScale(): array
    {
        return [
            '1' => 'Poor',
            '2' => 'Fair',
            '3' => 'Good',
            '4' => 'Very Good',
            '5' => 'Excellent',
        ];
    }

    /**
     * @return array<string, array{title: string, questions: array<string, string>}>
     */
    public static function sections(): array
    {
        return [
            'system_experience' => [
                'title' => 'Section 1 – System Experience',
                'questions' => [
                    'system_navigation' => 'The system was easy to navigate and use.',
                    'system_instructions' => 'The instructions and information provided by the system were clear and understandable.',
                    'submission_process' => 'The application submission process was straightforward.',
                    'status_information' => 'The system provided clear and timely information about the status of my application.',
                    'progress_monitoring' => 'The system made it convenient to monitor the progress of my application.',
                ],
            ],
            'ethics_review_process' => [
                'title' => 'Section 2 – Ethics Review Process',
                'questions' => [
                    'review_explanation' => 'The Ethics Review process was clearly explained to me.',
                    'requirements_clarity' => 'The review requirements and requested revisions were clear and understandable.',
                    'process_organization' => 'The Ethics Review process was organized and systematic.',
                    'response_convenience' => 'The system made it easy to respond to comments, revisions, or requirements from the Ethics Review process.',
                    'overall_satisfaction' => 'Overall, I am satisfied with my experience with the Ethics Review process.',
                ],
            ],
        ];
    }

    /** @return array<string, string> */
    public static function questions(): array
    {
        $questions = [];

        foreach (self::sections() as $section) {
            $questions += $section['questions'];
        }

        return $questions;
    }

    /** @return list<string> */
    public static function questionKeys(): array
    {
        return array_keys(self::questions());
    }
}
