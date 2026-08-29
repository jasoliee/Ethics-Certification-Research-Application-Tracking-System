<?php

namespace App\Services\Settings;

use App\Enums\AcademicTermStatus;
use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves the actual current term and applies shared historical term filters.
 */
class AcademicTermResolver
{
    public const FALLBACK_LABEL = 'Semester and Academic Year';

    private bool $currentResolved = false;

    private ?AcademicTerm $currentTerm = null;

    private ?bool $configuredTermsExist = null;

    public function current(): ?AcademicTerm
    {
        if ($this->currentResolved) {
            return $this->currentTerm;
        }

        $this->currentTerm = AcademicTerm::query()
            ->current()
            ->latest('starts_at')
            ->latest('id')
            ->first();
        $this->currentResolved = true;

        return $this->currentTerm;
    }

    public function latestConfigured(): ?AcademicTerm
    {
        return AcademicTerm::query()
            ->where('is_active', true)
            ->whereIn('status', [AcademicTermStatus::Active->value, AcademicTermStatus::Paused->value])
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    public function currentOrPaused(): ?AcademicTerm
    {
        $now = now();

        return AcademicTerm::query()
            ->where('is_active', true)
            ->whereIn('status', [AcademicTermStatus::Active->value, AcademicTermStatus::Paused->value])
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    public function hasConfiguredTerms(): bool
    {
        return $this->configuredTermsExist ??= AcademicTerm::query()->exists();
    }

    /**
     * @return Collection<int, AcademicTerm>
     */
    public function filterOptions(): Collection
    {
        $now = now();

        return AcademicTerm::query()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get(['id', 'semester', 'academic_year', 'starts_at', 'ends_at', 'is_active', 'status'])
            ->sortByDesc(fn (AcademicTerm $term): bool => $term->isCurrent($now))
            ->values();
    }

    /**
     * Apply independent semester and academic-year filters to a model with an academicTerm relation.
     *
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $academicTermId = $filters['academic_term_id'] ?? null;

        if (filled($academicTermId)) {
            return $query->where('academic_term_id', (int) $academicTermId);
        }

        $semester = trim((string) ($filters['semester'] ?? ''));
        $academicYear = trim((string) ($filters['academic_year'] ?? ''));

        if ($semester === '' && $academicYear === '') {
            return $query;
        }

        return $query->whereHas('academicTerm', function (Builder $terms) use ($semester, $academicYear): void {
            $terms
                ->when($semester !== '', fn (Builder $termQuery) => $termQuery->where('semester', $semester))
                ->when($academicYear !== '', fn (Builder $termQuery) => $termQuery->where('academic_year', $academicYear));
        });
    }
}
