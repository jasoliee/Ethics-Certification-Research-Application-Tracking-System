<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Defines the approved deadline processes shown in REU Lead Settings.
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
                'title' => 'REU Screening and Classification',
                'description' => 'Defines the planned period for REU completeness screening and review classification.',
                'audience_role' => UserRole::ResLead,
                'timeline_key' => 'res-screening',
                'timeline_label' => 'REU Screening',
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
            'revision-period' => [
                'title' => 'Revision Period',
                'description' => 'Defines when Applicants may submit revisions requested through the approved ethics review workflow.',
                'audience_role' => UserRole::Applicant,
                'timeline_key' => 'revision',
                'timeline_label' => 'Revision Period',
                'sort_order' => 4,
            ],
            'reviewing-revision-period' => [
                'title' => 'Reviewing of Revision Period',
                'description' => 'Defines when assigned ethics reviewers evaluate Applicant revision submissions.',
                'audience_role' => UserRole::Reviewer,
                'timeline_key' => 'reviewing-revision',
                'timeline_label' => 'Reviewing of Revision Period',
                'sort_order' => 5,
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
