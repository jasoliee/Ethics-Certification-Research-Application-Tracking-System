<?php

namespace App\Services\Applications;

use App\Enums\ReviewCommentCategory;
use App\Models\ApplicationDocument;
use App\Models\ResearchApplication;
use Illuminate\Support\Collection;

class RevisionRequirementSourceResolver
{
    /**
     * Resolve one current source document per requirement that must be revised.
     *
     * Required-revision document comments are authoritative. If a revision
     * decision contains only other document comments, those documents are used.
     * If the Reviewer supplied only overall/decision feedback, every current
     * document becomes actionable so the revision can never be created empty.
     *
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $feedback
     * @return Collection<int, array{requirement_id: int, source_document_id: int}>
     */
    public function resolve(
        ResearchApplication $application,
        Collection|array $feedback,
        bool $lockForUpdate = false,
    ): Collection {
        $comments = collect($feedback)
            ->filter(fn (mixed $comment): bool => is_array($comment) && blank(data_get($comment, 'deleted_at')))
            ->values();
        $documentsQuery = ApplicationDocument::query()
            ->where('research_application_id', $application->id)
            ->whereNotNull('document_requirement_id');
        if ($lockForUpdate) {
            $documentsQuery->lockForUpdate();
        }
        $documents = $documentsQuery
            ->orderBy('document_requirement_id')
            ->orderByDesc('document_version')
            ->orderByDesc('id')
            ->get();

        $requiredDocumentIds = $comments
            ->filter(function (array $comment): bool {
                $category = data_get($comment, 'category');

                return ($category instanceof ReviewCommentCategory ? $category->value : $category)
                    === ReviewCommentCategory::RequiredRevision->value;
            })
            ->pluck('application_document_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $commentedDocumentIds = $comments
            ->pluck('application_document_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $candidateDocumentIds = $requiredDocumentIds->isNotEmpty()
            ? $requiredDocumentIds
            : $commentedDocumentIds;
        $requirementIds = $documents
            ->whereIn('id', $candidateDocumentIds)
            ->pluck('document_requirement_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($requirementIds->isEmpty()) {
            $requirementIds = $documents
                ->where('is_current', true)
                ->pluck('document_requirement_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();
        }

        if ($requirementIds->isEmpty()) {
            $requirementIds = $documents
                ->pluck('document_requirement_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();
        }

        return $requirementIds->map(function (int $requirementId) use ($documents): ?array {
            $source = $documents
                ->where('document_requirement_id', $requirementId)
                ->sortByDesc(fn (ApplicationDocument $document): string => sprintf(
                    '%d-%010d-%010d',
                    $document->is_current ? 1 : 0,
                    (int) $document->document_version,
                    (int) $document->id,
                ))
                ->first();

            return $source ? [
                'requirement_id' => $requirementId,
                'source_document_id' => (int) $source->id,
            ] : null;
        })->filter()->values();
    }
}
