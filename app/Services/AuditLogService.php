<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\Settings\AcademicTermResolver;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Stringable;

class AuditLogService
{
    public function __construct(
        private readonly AcademicTermResolver $terms,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function record(
        ?User $actor,
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?AcademicTerm $academicTerm = null,
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;
        $metadata = $this->sanitizeMetadata($metadata);
        $subjectTerm = $this->subjectAcademicTerm($subject);
        $academicTermId = $academicTerm?->id
            ?? ($subjectTerm['resolved'] ? $subjectTerm['id'] : $this->terms->current()?->id);

        return AuditLog::create([
            'academic_term_id' => $academicTermId,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request
                ? mb_substr((string) $request->userAgent(), 0, 1000)
                : null,
        ]);
    }

    /** @return array{resolved: bool, id: int|null} */
    private function subjectAcademicTerm(?Model $subject): array
    {
        if ($subject instanceof ResearchApplication) {
            return [
                'resolved' => true,
                'id' => $subject->academic_term_id,
            ];
        }

        if ($subject instanceof ApplicationDocument) {
            return [
                'resolved' => true,
                'id' => ResearchApplication::query()
                    ->whereKey($subject->research_application_id)
                    ->value('academic_term_id'),
            ];
        }

        return ['resolved' => false, 'id' => null];
    }

    /** @param array<array-key, mixed> $metadata */
    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = match (true) {
                is_array($value) => $this->sanitizeMetadata($value),
                is_string($value) => mb_substr($value, 0, 2000),
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                $value instanceof Stringable => mb_substr((string) $value, 0, 2000),
                is_scalar($value), $value === null => $value,
                default => get_debug_type($value),
            };
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (['password', 'token', 'secret', 'credential', 'authorization', 'cookie', 'session', 'csrf', 'api_key'] as $term) {
            if (str_contains($key, $term)) {
                return true;
            }
        }

        return false;
    }
}
