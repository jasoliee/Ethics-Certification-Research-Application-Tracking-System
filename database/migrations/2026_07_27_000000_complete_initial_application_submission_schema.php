<?php

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add the application-information, draft-lock, and requirement-configuration fields needed by initial submission.
     */
    public function up(): void
    {
        // Extend existing application records instead of introducing a parallel application table.
        Schema::table('research_applications', function (Blueprint $table): void {
            $table->foreignId('draft_owner_user_id')
                ->nullable()
                ->after('applicant_user_id')
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('research_type', 30)->nullable()->after('research_title')->index();
            $table->string('research_category', 150)->nullable()->after('research_type');
            $table->string('institution', 150)->nullable()->after('research_category');
            $table->string('department', 150)->nullable()->after('institution');
            $table->string('program', 150)->nullable()->after('department');
            $table->text('abstract')->nullable()->after('program');
            $table->text('target_participants')->nullable()->after('abstract');
            $table->string('expected_duration', 120)->nullable()->after('target_participants');
            $table->string('current_stage', 50)
                ->default(ApplicationStage::ApplicationInformation->value)
                ->after('application_status')
                ->index();
        });

        // Let one shared requirement table express mandatory and research-type-specific configuration.
        Schema::table('document_requirements', function (Blueprint $table): void {
            $table->boolean('is_mandatory')->default(true)->after('description')->index();
            $table->json('research_types')->nullable()->after('is_mandatory');
        });

        // Preserve one editable legacy draft per applicant under the new database uniqueness guard.
        $draftStatuses = [
            ApplicationStatus::Draft->value,
            ApplicationStatus::Incomplete->value,
            ApplicationStatus::ReturnedByAdviser->value,
        ];
        $editableDrafts = DB::table('research_applications')
            ->whereIn('application_status', $draftStatuses)
            ->whereNull('submitted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'applicant_user_id'])
            ->unique('applicant_user_id');

        // Assign the unique draft owner only to the newest editable record for each applicant.
        foreach ($editableDrafts as $draft) {
            DB::table('research_applications')
                ->where('id', $draft->id)
                ->update(['draft_owner_user_id' => $draft->applicant_user_id]);
        }

        // Map existing records to a stable stage based on their authoritative application status.
        $stageStatuses = [
            ApplicationStage::ApplicationInformation->value => [
                ApplicationStatus::Draft->value,
                ApplicationStatus::Incomplete->value,
            ],
            ApplicationStage::AdviserReview->value => [
                ApplicationStatus::SubmittedToAdviser->value,
                ApplicationStatus::ReturnedByAdviser->value,
            ],
            ApplicationStage::ResScreening->value => [
                ApplicationStatus::AdviserEndorsed->value,
                ApplicationStatus::UnderResScreening->value,
                ApplicationStatus::AwaitingReviewerAssignment->value,
            ],
            ApplicationStage::EthicsReview->value => [
                ApplicationStatus::UnderExpeditedReview->value,
                ApplicationStatus::UnderFullBoardReview->value,
                ApplicationStatus::UnderReReview->value,
            ],
            ApplicationStage::Revision->value => [
                ApplicationStatus::ResultReleasedMinorRevision->value,
                ApplicationStatus::ResultReleasedMajorRevision->value,
                ApplicationStatus::RevisionWindowOpen->value,
                ApplicationStatus::RevisionSubmitted->value,
                ApplicationStatus::FeedbackRequired->value,
            ],
            ApplicationStage::DecisionRelease->value => [
                ApplicationStatus::ReviewSubmittedPendingRelease->value,
                ApplicationStatus::ResultReleasedAccepted->value,
                ApplicationStatus::ResultReleasedDisapproved->value,
            ],
            ApplicationStage::Completed->value => [
                ApplicationStatus::CertificateReleased->value,
                ApplicationStatus::Archived->value,
            ],
        ];

        // Update stages in bounded status groups to avoid loading application records into PHP memory.
        foreach ($stageStatuses as $stage => $statuses) {
            DB::table('research_applications')
                ->whereIn('application_status', $statuses)
                ->update(['current_stage' => $stage]);
        }

        // Add only the approved Student example values missing from the database-backed dropdown catalog.
        $profileDefaults = [
            'department' => ['Computer Studies'],
            'program' => ['Bachelor of Science in Computer Science'],
        ];
        $now = now();

        // Preserve team-managed options by inserting defaults only when the normalized field value is absent.
        foreach ($profileDefaults as $field => $values) {
            foreach ($values as $index => $value) {
                DB::table('profile_options')->insertOrIgnore([
                    'field' => $field,
                    'value' => $value,
                    'normalized_value' => Str::lower($value),
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Remove only the additive schema while preserving shared catalog data.
     */
    public function down(): void
    {
        // Catalog rows have no reliable migration-ownership marker, so rollback leaves them intact.

        // Restore the original global active-requirement schema.
        Schema::table('document_requirements', function (Blueprint $table): void {
            $table->dropIndex(['is_mandatory']);
            $table->dropColumn(['is_mandatory', 'research_types']);
        });

        // Remove only the additive application-submission columns and their indexes.
        Schema::table('research_applications', function (Blueprint $table): void {
            $table->dropIndex(['research_type']);
            $table->dropIndex(['current_stage']);
            $table->dropUnique(['draft_owner_user_id']);
            $table->dropConstrainedForeignId('draft_owner_user_id');
            $table->dropColumn([
                'research_type',
                'research_category',
                'institution',
                'department',
                'program',
                'abstract',
                'target_participants',
                'expected_duration',
                'current_stage',
            ]);
        });
    }
};
