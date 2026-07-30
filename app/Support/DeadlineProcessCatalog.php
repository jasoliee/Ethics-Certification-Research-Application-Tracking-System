<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Defines the approved deadline processes shown in RES Lead Settings.
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
     *     sort_order: int,
     *     exact_date: bool
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
                'exact_date' => false,
            ],
            'adviser-endorsement' => [
                'title' => 'Adviser Endorsement',
                'description' => 'Defines the planned period for Adviser review, return, or endorsement.',
                'audience_role' => UserRole::Adviser,
                'timeline_key' => 'endorsement',
                'timeline_label' => 'Endorsement Period',
                'sort_order' => 1,
                'exact_date' => false,
            ],
            'res-screening' => [
                'title' => 'RES Screening and Classification',
                'description' => 'Defines the planned period for RES completeness screening and review classification.',
                'audience_role' => UserRole::ResLead,
                'timeline_key' => 'res-screening',
                'timeline_label' => 'RES Screening',
                'sort_order' => 2,
                'exact_date' => false,
            ],
            'reviewer-submission' => [
                'title' => 'Reviewer Submission',
                'description' => 'Defines the planned period for assigned ethics reviewers to complete review work.',
                'audience_role' => UserRole::Reviewer,
                'timeline_key' => 'reviewing',
                'timeline_label' => 'Reviewing Period',
                'sort_order' => 3,
                'exact_date' => false,
            ],
            'revision-period' => [
                'title' => 'Revision Period',
                'description' => 'Defines when Applicants may submit revisions requested through the approved ethics review workflow.',
                'audience_role' => UserRole::Applicant,
                'timeline_key' => 'revision',
                'timeline_label' => 'Revision Period',
                'sort_order' => 4,
                'exact_date' => false,
            ],
            'reviewing-revision-period' => [
                'title' => 'Reviewing of Revision Period',
                'description' => 'Defines when assigned ethics reviewers evaluate Applicant revision submissions.',
                'audience_role' => UserRole::Reviewer,
                'timeline_key' => 'reviewing-revision',
                'timeline_label' => 'Reviewing of Revision Period',
                'sort_order' => 5,
                'exact_date' => false,
            ],
            'result-release' => [
                'title' => 'Release of Decision & Certificate',
                'description' => 'Sets the exact date and time when RES releases decisions and eligible certificates.',
                'audience_role' => UserRole::ResLead,
                'timeline_key' => 'release',
                'timeline_label' => 'Release of Decision & Certificate',
                'sort_order' => 6,
                'exact_date' => true,
            ],
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Resolve overlapping stored suffixes by preferring the longest approved process key.
     */
    public static function keyForDeadlineKey(string $deadlineKey): ?string
    {
        $keys = self::keys();
        usort($keys, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($keys as $key) {
            if ($deadlineKey === $key || str_ends_with($deadlineKey, $key)) {
                return $key;
            }
        }

        return null;
    }
}
