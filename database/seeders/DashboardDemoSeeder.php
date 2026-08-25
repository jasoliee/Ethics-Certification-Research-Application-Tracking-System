<?php

namespace Database\Seeders;

use App\Enums\ApplicantType;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
use App\Enums\RequirementStatus;
use App\Enums\ResearchType;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\ApplicationScreening;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\TimelineCalendarEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Dashboard demo data is available only in local and testing environments.');

            return;
        }

        // Reuse stable account and requirement seeders before adding local-only dashboard examples.
        $this->call([
            ResLeadSeeder::class,
            TestingUserSeeder::class,
            ApplicationConfigurationSeeder::class,
        ]);

        $applicant = User::where('username', 'applicanttest')->firstOrFail();
        $adviser = User::where('username', 'advisertest')->firstOrFail();
        $reviewer = User::where('username', 'reviewertest')->firstOrFail();
        $resLead = User::where('username', 'reslead')->firstOrFail();

        $additionalApplicants = collect([
            ['name' => 'Juan Dela Cruz', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'institutional_identifier' => 'KLD-STU-101', 'username' => 'demostudent1', 'email' => 'demostudent1@ecrats.test', 'applicant_type' => ApplicantType::Student],
            ['name' => 'Maria Santos', 'first_name' => 'Maria', 'last_name' => 'Santos', 'institutional_identifier' => 'KLD-STU-102', 'username' => 'demostudent2', 'email' => 'demostudent2@ecrats.test', 'applicant_type' => ApplicantType::Student],
            ['name' => 'Carla De Leon', 'first_name' => 'Carla', 'last_name' => 'De Leon', 'institutional_identifier' => 'KLD-STU-103', 'username' => 'demostudent3', 'email' => 'demostudent3@ecrats.test', 'applicant_type' => ApplicantType::Student],
            ['name' => 'Mark Rivera', 'first_name' => 'Mark', 'last_name' => 'Rivera', 'institutional_identifier' => 'KLD-EMP-103', 'username' => 'demofaculty1', 'email' => 'demofaculty1@ecrats.test', 'applicant_type' => ApplicantType::Faculty],
        ])->map(fn (array $data): User => User::updateOrCreate(
            ['username' => $data['username']],
            [
                ...$data,
                'middle_name' => 'M.',
                'suffix' => null,
                'institution' => 'Institute of Computing and Digital Innovation',
                'program' => 'Bachelor of Science in Computer Science',
                'year_level' => '1st Year',
                'phone_number' => '0917'.str_pad((string) (1000000 + (int) substr($data['institutional_identifier'], -3)), 7, '0', STR_PAD_LEFT),
                'position_title' => $data['applicant_type'] === ApplicantType::Faculty ? 'Faculty Researcher' : 'Student Researcher',
                'password' => Hash::make('12345678'),
                'password_changed_at' => now(),
                'role' => UserRole::Applicant,
                'account_status' => 'active',
                'created_by_user_id' => $resLead->id,
            ],
        ));

        // Load the four stable baseline requirements in their configured display order.
        $requirements = DocumentRequirement::query()
            ->whereIn('code', [
                'RESEARCH-PROPOSAL',
                'KLD-RES-04-001B',
                'KLD-RES-04-003',
                'PAYMENT-PROOF',
            ])
            ->orderBy('sort_order')
            ->get();

        $primary = $this->application(
            'ECRATS-DEMO-0001',
            $applicant,
            $adviser,
            'Exploring Mobile Learning Tools and Student Engagement',
            ApplicationStatus::UnderResScreening,
            'student',
            null,
            now()->subDays(4),
        );

        collect([
            $primary,
            $this->application('ECRATS-DEMO-0002', $additionalApplicants[0], $adviser, 'Digital Learning Habits and Academic Adjustment', ApplicationStatus::SubmittedToAdviser, 'student', null, now()->subDays(2)),
            $this->application('ECRATS-DEMO-0003', $additionalApplicants[1], $adviser, 'Community Preparedness for Flood Risk', ApplicationStatus::AwaitingReviewerAssignment, 'student', 'expedited', now()->subDays(5)),
            $this->application('ECRATS-DEMO-0004', $additionalApplicants[2], $adviser, 'Student Well-Being and Study Stress', ApplicationStatus::UnderFullBoardReview, 'student', 'full_board', now()->subDays(7)),
            $this->application('ECRATS-DEMO-0005', $additionalApplicants[3], $adviser, 'Classroom Inclusion Practices in Senior High', ApplicationStatus::ReviewSubmittedPendingRelease, 'faculty', 'expedited', now()->subDays(9)),
            $this->application('ECRATS-DEMO-0006', $additionalApplicants[0], $adviser, 'Water Quality Monitoring in Urban Communities', ApplicationStatus::ReturnedByAdviser, 'student', null, now()->subDays(3)),
            $this->application('ECRATS-DEMO-0007', $additionalApplicants[1], $adviser, 'Inclusive Inventory Systems for Campus Services', ApplicationStatus::AdviserEndorsed, 'student', null, now()->subDay()),
        ]);

        $reviewApplications = collect([
            $this->application('ECRATS-DEMO-0008', $additionalApplicants[0], $adviser, 'Community Health Information Access', ApplicationStatus::UnderExpeditedReview, 'student', 'expedited', now()->subDays(8)),
            $this->application('ECRATS-DEMO-0009', $additionalApplicants[1], $adviser, 'Learning Analytics and Student Privacy', ApplicationStatus::UnderFullBoardReview, 'student', 'full_board', now()->subDays(7)),
            $this->application('ECRATS-DEMO-0010', $additionalApplicants[2], $adviser, 'Study Stress Intervention Follow-up', ApplicationStatus::UnderReReview, 'student', 'expedited', now()->subDays(6)),
            $this->application('ECRATS-DEMO-0011', $additionalApplicants[3], $adviser, 'Faculty Research Data Governance', ApplicationStatus::ReviewSubmittedPendingRelease, 'faculty', 'expedited', now()->subDays(5)),
        ]);

        foreach ($requirements as $index => $requirement) {
            ApplicationDocument::updateOrCreate(
                [
                    'research_application_id' => $primary->id,
                    'document_requirement_id' => $requirement->id,
                    'document_version' => 1,
                ],
                [
                    'uploaded_by_user_id' => $applicant->id,
                    'original_file_name' => str($requirement->code)->lower().'.pdf',
                    'stored_file_path' => 'demo/applications/'.$primary->application_code.'/'.str($requirement->code)->lower().'.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size_bytes' => 1024 * ($index + 1),
                    'validation_status' => $index < 3 ? RequirementStatus::Completed : RequirementStatus::Pending,
                    'is_current' => true,
                    'uploaded_at' => now()->subDays(4),
                ],
            );
        }

        $assignmentStates = [
            ReviewerAssignmentStatus::Pending,
            ReviewerAssignmentStatus::InReview,
            ReviewerAssignmentStatus::RevisionReview,
            ReviewerAssignmentStatus::DecisionSubmitted,
        ];

        foreach ($assignmentStates as $index => $status) {
            $application = $reviewApplications[$index];

            ReviewerAssignment::updateOrCreate(
                [
                    'research_application_id' => $application->id,
                    'reviewer_user_id' => $reviewer->id,
                    'review_type' => $status === ReviewerAssignmentStatus::RevisionReview ? 'revision_review' : 'initial_review',
                ],
                [
                    'assignment_status' => $status,
                    'assigned_at' => now()->subDays(4 - $index),
                    'review_deadline_at' => now()->addDays($index + 1)->endOfDay(),
                    'submitted_at' => $status === ReviewerAssignmentStatus::DecisionSubmitted ? now()->subHour() : null,
                ],
            );
        }

        // Backfill the prerequisite records represented by later demo statuses so every linked screen remains usable.
        $this->seedResWorkflowContext($resLead, $adviser);

        $this->seedDeadlines();
        $this->seedTimeline();

        foreach ([$applicant, $adviser, $reviewer, $resLead] as $index => $user) {
            DB::table('notifications')->updateOrInsert(
                ['id' => sprintf('10000000-0000-4000-8000-%012d', $index + 1)],
                [
                    'type' => 'App\\Notifications\\DashboardUpdateNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'title' => 'Application status updated',
                        'message' => 'A research ethics application has a new workflow status.',
                        'icon' => 'clipboard',
                        'tone' => 'blue',
                    ], JSON_THROW_ON_ERROR),
                    'read_at' => null,
                    'created_at' => now()->subMinutes(($index + 1) * 5),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function application(
        string $code,
        User $applicant,
        User $adviser,
        string $title,
        ApplicationStatus $status,
        string $applicantType,
        ?string $reviewType,
        $submittedAt,
    ): ResearchApplication {
        return ResearchApplication::updateOrCreate(
            ['application_code' => $code],
            [
                'applicant_user_id' => $applicant->id,
                'adviser_user_id' => $adviser->id,
                'applicant_type' => $applicantType,
                'research_title' => $title,
                'research_type' => ResearchType::Thesis,
                'research_category' => 'Social and Behavioral Research',
                'institution' => $applicant->institution,
                'program' => $applicant->program,
                'abstract' => 'A local demonstration application used to verify role dashboards and application visibility.',
                'target_participants' => 'Eligible KLD students, faculty members, or community participants.',
                'expected_duration' => 'August 2026 to May 2027',
                'expected_start_date' => '2026-08-01',
                'expected_end_date' => '2027-05-31',
                'application_type' => 'new_application',
                'application_status' => $status,
                'current_stage' => $this->stageFor($status),
                'review_type' => $reviewType,
                'submitted_at' => $submittedAt,
                'status_updated_at' => now(),
            ],
        );
    }

    /**
     * Keep local demonstration stages aligned with each authoritative application status.
     */
    private function stageFor(ApplicationStatus $status): ApplicationStage
    {
        return match ($status) {
            ApplicationStatus::Draft, ApplicationStatus::Incomplete => ApplicationStage::ApplicationInformation,
            ApplicationStatus::SubmittedToAdviser, ApplicationStatus::ReturnedByAdviser => ApplicationStage::AdviserReview,
            ApplicationStatus::AdviserEndorsed,
            ApplicationStatus::UnderResScreening,
            ApplicationStatus::AwaitingReviewerAssignment => ApplicationStage::ResScreening,
            ApplicationStatus::UnderExpeditedReview,
            ApplicationStatus::UnderFullBoardReview,
            ApplicationStatus::UnderReReview => ApplicationStage::EthicsReview,
            ApplicationStatus::ResultReleasedMinorRevision,
            ApplicationStatus::ResultReleasedMajorRevision,
            ApplicationStatus::RevisionWindowOpen,
            ApplicationStatus::RevisionSubmitted,
            ApplicationStatus::FeedbackRequired => ApplicationStage::Revision,
            ApplicationStatus::ReviewSubmittedPendingRelease,
            ApplicationStatus::ResultReleasedAccepted,
            ApplicationStatus::Exempted,
            ApplicationStatus::ResultReleasedDisapproved => ApplicationStage::DecisionRelease,
            ApplicationStatus::CertificateReleased, ApplicationStatus::Archived => ApplicationStage::Completed,
        };
    }

    private function seedDeadlines(): void
    {
        $deadlines = [
            ['application-submission', 'Application submission deadline', UserRole::Applicant, 2, 5],
            ['adviser-endorsement', 'Remaining days to complete endorsement', UserRole::Adviser, 2, 5],
            ['reviewer-submission', 'Reviewer submission deadline', UserRole::Reviewer, 2, 5],
            ['res-screening', 'RES screening and classification deadline', UserRole::ResLead, 4, 4],
        ];

        foreach ($deadlines as [$key, $title, $role, $days, $priority]) {
            DeadlineConfiguration::updateOrCreate(
                ['deadline_key' => 'demo-'.$key],
                [
                    'title' => $title,
                    'audience_role' => $role,
                    'starts_at' => now()->subDay(),
                    'due_at' => now()->addDays($days)->endOfDay(),
                    'priority' => $priority,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Preserve idempotent Adviser endorsement and RES classification history for post-screening demo records.
     */
    private function seedResWorkflowContext(User $resLead, User $adviser): void
    {
        $applications = ResearchApplication::query()
            ->where('application_code', 'like', 'ECRATS-DEMO-%')
            ->whereIn('application_status', ApplicationStatus::afterAdviserEndorsement())
            ->get();

        foreach ($applications as $application) {
            Endorsement::updateOrCreate(
                [
                    'research_application_id' => $application->id,
                    'adviser_user_id' => $adviser->id,
                    'endorsement_status' => EndorsementStatus::Endorsed->value,
                ],
                [
                    'endorsement_remarks' => 'Demo application endorsed for RES processing.',
                    'endorsed_at' => $application->submitted_at?->copy()->addDay() ?? now(),
                ],
            );

            $reviewType = ReviewType::tryFrom((string) $application->review_type);

            if (! $reviewType) {
                continue;
            }

            ApplicationScreening::updateOrCreate(
                ['research_application_id' => $application->id],
                [
                    'screened_by_user_id' => $resLead->id,
                    'review_type' => $reviewType->value,
                    'classification_reason' => 'Demo classification retained to support the represented workflow status.',
                    'classified_at' => $application->submitted_at?->copy()->addDays(2) ?? now(),
                ],
            );
        }
    }

    private function seedTimeline(): void
    {
        $events = [
            ['submission', 'Submission of Application', -10, -10],
            ['endorsement', 'Endorsement Period', -9, -7],
            ['res-screening', 'RES Screening', -6, -3],
            ['reviewing', 'Reviewing Period', -2, 7],
            ['revision', 'Revision Period', 8, 14],
        ];

        foreach ($events as $index => [$key, $label, $start, $end]) {
            TimelineCalendarEvent::updateOrCreate(
                ['milestone_key' => 'demo-'.$key],
                [
                    'label' => $label,
                    'term_label' => '1st Semester, A.Y. 2026-2027',
                    'starts_at' => now()->startOfDay()->addDays($start),
                    'ends_at' => now()->endOfDay()->addDays($end),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
