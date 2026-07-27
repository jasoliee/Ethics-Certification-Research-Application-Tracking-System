<?php

namespace App\Services\Applications;

use App\Enums\RequirementStatus;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Support\DocumentTypeIcon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Calculates one authoritative requirement checklist for dashboards, uploads, and submission.
 */
class ApplicationRequirementService
{
    /**
     * Build the active research-type-aware requirement state without queries in Blade templates.
     *
     * @return array{
     *     items: Collection<int, array<string, mixed>>,
     *     mandatory_total: int,
     *     completed_count: int,
     *     pending_count: int,
     *     rejected_count: int,
     *     missing_count: int,
     *     percentage: int,
     *     ready: bool
     * }
     */
    public function summary(ResearchApplication $application): array
    {
        // Load current document versions in the same bounded query as active requirement configuration.
        $requirements = DocumentRequirement::query()
            ->select([
                'id',
                'code',
                'name',
                'description',
                'is_mandatory',
                'research_types',
                'sort_order',
            ])
            ->where('is_active', true)
            ->with(['applicationDocuments' => fn ($query) => $query
                ->select([
                    'id',
                    'research_application_id',
                    'document_requirement_id',
                    'uploaded_by_user_id',
                    'original_file_name',
                    'mime_type',
                    'file_size_bytes',
                    'document_version',
                    'validation_status',
                    'uploaded_at',
                    'is_current',
                ])
                ->where('research_application_id', $application->id)
                ->where('is_current', true)
                ->latest('document_version')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (DocumentRequirement $requirement): bool => $requirement->appliesTo($application->research_type))
            ->values();

        // Normalize each requirement and its current file into one display and validation payload.
        $items = $requirements->map(function (DocumentRequirement $requirement): array {
            $document = $requirement->applicationDocuments->first();
            $status = $document?->validation_status ?? RequirementStatus::Missing;

            return [
                'requirement' => $requirement,
                'document' => $document,
                'status' => $status,
                'completed' => $status === RequirementStatus::Completed,
                'icon' => DocumentTypeIcon::fromMimeType($document?->mime_type),
            ];
        });
        $mandatoryItems = $items->filter(fn (array $item): bool => $item['requirement']->is_mandatory);
        $mandatoryTotal = $mandatoryItems->count();
        $completedCount = $mandatoryItems->where('status', RequirementStatus::Completed)->count();

        // Percentage and readiness both derive exclusively from active mandatory requirements.
        return [
            'items' => $items,
            'mandatory_total' => $mandatoryTotal,
            'completed_count' => $completedCount,
            'pending_count' => $mandatoryItems->where('status', RequirementStatus::Pending)->count(),
            'rejected_count' => $mandatoryItems->where('status', RequirementStatus::Rejected)->count(),
            'missing_count' => $mandatoryItems->where('status', RequirementStatus::Missing)->count(),
            'percentage' => $mandatoryTotal > 0
                ? (int) floor(($completedCount / $mandatoryTotal) * 100)
                : 0,
            'ready' => $mandatoryTotal > 0 && $completedCount === $mandatoryTotal,
        ];
    }

    /**
     * Block final submission until every applicable mandatory requirement is complete.
     */
    public function assertReady(ResearchApplication $application): void
    {
        $summary = $this->summary($application);

        // A missing configuration is a blocking administrative issue, not an empty successful checklist.
        if ($summary['mandatory_total'] === 0) {
            throw ValidationException::withMessages([
                'requirements' => 'Application requirements are not configured for this research type.',
            ]);
        }

        // Report only safe requirement names while retaining rejected and pending states as incomplete.
        if (! $summary['ready']) {
            $notReady = $summary['items']
                ->filter(fn (array $item): bool => $item['requirement']->is_mandatory && ! $item['completed'])
                ->map(fn (array $item): string => $item['requirement']->name)
                ->values();

            throw ValidationException::withMessages([
                'requirements' => 'Complete every mandatory requirement before submission. Not ready: '.$notReady->join(', ').'.',
            ]);
        }
    }
}
