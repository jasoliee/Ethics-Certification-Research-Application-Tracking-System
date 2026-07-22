<?php

namespace App\Support;

use App\Enums\ApplicantType;
use App\Enums\UserRole;
use App\Models\User;

class OnboardingGuide
{
    /** @return array{title: string, introduction: string, steps: array<int, array{title: string, description: string}>, support: string} */
    public static function for(User $user): array
    {
        return match ($user->role) {
            UserRole::Applicant => self::applicant($user->applicant_type ?? ApplicantType::Student),
            UserRole::Adviser => self::guide(
                'Research Adviser Guide',
                'Use ECRATS to manage applicant accounts and review complete submissions assigned to you.',
                [
                    ['title' => 'Manage applicants', 'description' => 'Create student or faculty accounts and resend setup links when needed.'],
                    ['title' => 'Review submissions', 'description' => 'Check required documents and research details before endorsement.'],
                    ['title' => 'Return or endorse', 'description' => 'Return incomplete work with clear guidance or endorse a complete initial submission to RES.'],
                    ['title' => 'Watch deadlines', 'description' => 'Use dashboard alerts and notifications to keep assigned applications moving.'],
                ],
            ),
            UserRole::Reviewer => self::guide(
                'Ethics Reviewer Guide',
                'Review only assigned anonymized applications and protect applicant and reviewer confidentiality.',
                [
                    ['title' => 'Check assignments', 'description' => 'Open Assignments and declare any conflict before accessing full review materials.'],
                    ['title' => 'Complete the worksheet', 'description' => 'Review the protocol, record comments, and complete every required decision field.'],
                    ['title' => 'Meet the deadline', 'description' => 'Use the dashboard deadline alert and submit the review before the assigned due date.'],
                    ['title' => 'Review revisions', 'description' => 'Respond to routed revisions without revealing your identity to applicants.'],
                ],
            ),
            UserRole::ResLead => self::guide(
                'RES Lead Guide',
                'Coordinate screening, reviewer assignments, result release, account administration, and audit-ready records.',
                [
                    ['title' => 'Screen applications', 'description' => 'Verify endorsed applications and classify the approved review pathway.'],
                    ['title' => 'Manage reviewers', 'description' => 'Assign qualified reviewers while checking classification, capacity, and conflicts.'],
                    ['title' => 'Release official outcomes', 'description' => 'Monitor completed reviews and release only authorized decisions and documents.'],
                    ['title' => 'Administer accounts', 'description' => 'Create non-RES accounts, monitor setup delivery, and use controlled status or archive actions.'],
                ],
            ),
        };
    }

    /** @return array{title: string, introduction: string, steps: array<int, array{title: string, description: string}>, support: string} */
    private static function applicant(ApplicantType $type): array
    {
        return self::guide(
            $type === ApplicantType::Student ? 'Student Researcher Guide' : 'Faculty Researcher Guide',
            'Your account was created for you. Begin by reviewing your profile, then prepare a complete ethics application.',
            [
                ['title' => 'Set up and sign in', 'description' => 'Use the one-time email link to choose your password, then sign in with the username from that email.'],
                ['title' => 'Review your profile', 'description' => 'Confirm your institutional and contact information after signing in.'],
                ['title' => 'Enter research information', 'description' => 'Create a draft and provide the required project and adviser details.'],
                ['title' => 'Complete requirements', 'description' => 'Upload every mandatory document and resolve pending or rejected items.'],
                ['title' => 'Submit and monitor', 'description' => 'Submit only when the checklist is complete, then follow status updates and deadlines.'],
                ['title' => 'Respond and access results', 'description' => 'Submit requested revisions and access released decisions or certificates.'],
            ],
        );
    }

    /** @param array<int, array{title: string, description: string}> $steps */
    private static function guide(string $title, string $introduction, array $steps): array
    {
        return [
            'title' => $title,
            'introduction' => $introduction,
            'steps' => $steps,
            'support' => 'For account or workflow help, contact the KLD Research Ethics Section through the institutional contact channels in the footer.',
        ];
    }
}
