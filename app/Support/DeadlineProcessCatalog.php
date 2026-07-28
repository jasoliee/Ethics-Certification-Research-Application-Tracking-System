<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Defines only the deadline processes already established by repository seed logic.
 */
final class DeadlineProcessCatalog
{
    /**
     * @return array<string, array{
     *     title: string,
     *     description: string,
     *     audience_role: UserRole,
     *     timeline_key: string,
     *     timeline_label: string,
     *     sort_order: int
     * }>
     */
    public static function definitions(): array
    {
        return [
            'application-submission' => [
                'title' => 'Application Submission',
                'description' => 'Controls when Applicants may formally submit complete applications to their Research Adviser.',
                'audience_role' => UserRole::Applicant,
                'timeline_key' => 'submission',
                'timeline_label' => 'Submission of Application',
                'sort_order' => 0,
            ],
            'adviser-endorsement' => [
                'title' => 'Adviser Endorsement',
                'description' => 'Defines the planned period for Adviser review, return, or endorsement.',
                'audience_role' => UserRole::Adviser,
                'timeline_key' => 'endorsement',
                'timeline_label' => 'Endorsement Period',
                'sort_order' => 1,
            ],
            'res-screening' => [
                'title' => 'RES Screening and Classification',
                'description' => 'Defines the planned period for RES completeness screening and review classification.',
                'audience_role' => UserRole::ResLead,
                'timeline_key' => 'res-screening',
                'timeline_label' => 'RES Screening',
                'sort_order' => 2,
            ],
            'reviewer-submission' => [
                'title' => 'Reviewer Submission',
                'description' => 'Defines the planned period for assigned ethics reviewers to complete review work.',
                'audience_role' => UserRole::Reviewer,
                'timeline_key' => 'reviewing',
                'timeline_label' => 'Reviewing Period',
                'sort_order' => 3,
            ],
            'result-release' => [
                'title' => 'Result and Certificate Release',
                'description' => 'Defines the planned release period after required review and pre-release steps.',
                'audience_role' => UserRole::ResLead,
                'timeline_key' => 'release',
                'timeline_label' => 'Release of Decision & Certificate',
                'sort_order' => 5,
            ],
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }
}
