<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Incomplete = 'incomplete';
    case SubmittedToAdviser = 'submitted_to_adviser';
    case ReturnedByAdviser = 'returned_by_adviser';
    case AdviserEndorsed = 'adviser_endorsed';
    case UnderResScreening = 'under_res_screening';
    case AwaitingReviewerAssignment = 'awaiting_reviewer_assignment';
    case UnderExpeditedReview = 'under_expedited_review';
    case UnderFullBoardReview = 'under_full_board_review';
    case Exempted = 'exempted';
    case ReviewSubmittedPendingRelease = 'review_submitted_pending_release';
    case ResultReleasedAccepted = 'result_released_accepted';
    case ForCertificateRelease = 'for_certificate_release';
    case ResultReleasedMinorRevision = 'result_released_minor_revision';
    case ResultReleasedMajorRevision = 'result_released_major_revision';
    case ResultReleasedDisapproved = 'result_released_disapproved';
    case Failed = 'failed';
    case RevisionWindowOpen = 'revision_window_open';
    case RevisionSubmitted = 'revision_submitted';
    case UnderReReview = 'under_re_review';
    case FeedbackRequired = 'feedback_required';
    case CertificateReleased = 'certificate_released';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Incomplete => 'Incomplete',
            self::SubmittedToAdviser => 'Pending Adviser Review',
            self::ReturnedByAdviser => 'Returned',
            self::AdviserEndorsed => 'For REU Screening',
            self::UnderResScreening => 'Under REU Screening',
            self::AwaitingReviewerAssignment => 'Awaiting Assignment',
            self::UnderExpeditedReview => 'Under Expedited Review',
            self::UnderFullBoardReview => 'Under Full Board Review',
            self::Exempted => 'Exempted',
            self::ReviewSubmittedPendingRelease => 'Pending Decision Release',
            self::ResultReleasedAccepted => 'Accepted',
            self::ForCertificateRelease => 'For Certificate Release',
            self::ResultReleasedMinorRevision => 'Minor Revision',
            self::ResultReleasedMajorRevision => 'Major Revision',
            self::ResultReleasedDisapproved => 'Disapproved',
            self::Failed => 'Failed',
            self::RevisionWindowOpen => 'Revision Window Open',
            self::RevisionSubmitted => 'Revision Submitted',
            self::UnderReReview => 'Under Re-review',
            self::FeedbackRequired => 'Feedback Required',
            self::CertificateReleased => 'Certificate Released',
            self::Archived => 'Archived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Incomplete, self::Archived => 'neutral',
            self::SubmittedToAdviser, self::AdviserEndorsed, self::FeedbackRequired => 'orange',
            self::UnderResScreening, self::AwaitingReviewerAssignment => 'blue',
            self::UnderExpeditedReview, self::RevisionSubmitted, self::UnderReReview => 'green',
            self::UnderFullBoardReview, self::RevisionWindowOpen => 'violet',
            self::Exempted, self::ReviewSubmittedPendingRelease => 'cyan',
            self::ReturnedByAdviser, self::ResultReleasedDisapproved, self::Failed => 'red',
            self::ResultReleasedAccepted, self::ForCertificateRelease, self::CertificateReleased => 'success',
            self::ResultReleasedMinorRevision => 'amber',
            self::ResultReleasedMajorRevision => 'purple',
        };
    }

    /** @return array<int, self> */
    public static function underReview(): array
    {
        return [self::UnderExpeditedReview, self::UnderFullBoardReview, self::UnderReReview];
    }

    /** @return array<int, self> */
    public static function afterAdviserEndorsement(): array
    {
        return [
            self::AdviserEndorsed,
            self::UnderResScreening,
            self::AwaitingReviewerAssignment,
            ...self::underReview(),
            self::Exempted,
            self::ReviewSubmittedPendingRelease,
            self::ResultReleasedAccepted,
            self::ForCertificateRelease,
            self::ResultReleasedMinorRevision,
            self::ResultReleasedMajorRevision,
            self::ResultReleasedDisapproved,
            self::Failed,
            self::RevisionWindowOpen,
            self::RevisionSubmitted,
            self::FeedbackRequired,
            self::CertificateReleased,
            self::Archived,
        ];
    }

    /** @return array<int, string> */
    public static function values(array $statuses): array
    {
        return array_map(static fn (self $status): string => $status->value, $statuses);
    }
}
