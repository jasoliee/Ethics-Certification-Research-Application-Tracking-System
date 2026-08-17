<?php

namespace App\Services\Certificates;

use App\Enums\UserRole;
use App\Models\CertificateBackground;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Throwable;

class CertificateBackgroundService
{
    public const OFFICIAL_RESOURCE = 'resources/certificates/res-certificate-background.jpeg';

    public const OFFICIAL_REVIEW_WORKSHEET_RESOURCE = 'resources/assets/official/ovprii-document-background.png';

    public const OFFICIAL_SOURCE_KIND = 'official_default';

    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function active(string $type = CertificateBackground::TYPE_CERTIFICATE): CertificateBackground
    {
        $this->assertType($type);
        $active = CertificateBackground::query()
            ->where('background_type', $type)
            ->where('is_active', true)
            ->latest('activated_at')
            ->first();

        return $active && $this->isIntact($active)
            ? $active
            : $this->ensureOfficialDefault($type);
    }

    public function ensureOfficialDefault(string $type = CertificateBackground::TYPE_CERTIFICATE): CertificateBackground
    {
        $this->assertType($type);
        $resource = base_path($type === CertificateBackground::TYPE_CERTIFICATE
            ? self::OFFICIAL_RESOURCE
            : self::OFFICIAL_REVIEW_WORKSHEET_RESOURCE);
        if (! is_readable($resource)) {
            throw ValidationException::withMessages([
                'background' => 'The verified official background is unavailable.',
            ])->errorBag('certificateBackground');
        }

        $hash = hash_file('sha256', $resource);
        $dimensions = getimagesize($resource);
        $expectedMime = $type === CertificateBackground::TYPE_CERTIFICATE ? 'image/jpeg' : 'image/png';
        if (! is_string($hash) || ! is_array($dimensions) || ($dimensions['mime'] ?? null) !== $expectedMime) {
            throw ValidationException::withMessages([
                'background' => 'The verified official background is unavailable.',
            ])->errorBag('certificateBackground');
        }

        return DB::transaction(function () use ($resource, $hash, $dimensions, $type, $expectedMime): CertificateBackground {
            $backgrounds = CertificateBackground::query()->orderBy('id')->lockForUpdate()->get();
            $typedBackgrounds = $backgrounds->where('background_type', $type);
            $official = $typedBackgrounds->firstWhere('source_kind', self::OFFICIAL_SOURCE_KIND);
            $extension = ($dimensions['mime'] ?? null) === 'image/png' ? 'png' : 'jpeg';
            $path = "managed-backgrounds/{$type}/official-{$hash}.{$extension}";

            $storedHash = Storage::disk('local')->exists($path)
                ? hash_file('sha256', Storage::disk('local')->path($path))
                : false;
            if ((! is_string($storedHash) || ! hash_equals($hash, $storedHash))
                && ! Storage::disk('local')->put($path, file_get_contents($resource))) {
                throw ValidationException::withMessages([
                    'background' => 'The official background could not be prepared securely.',
                ])->errorBag('certificateBackground');
            }

            if (! $official) {
                $official = CertificateBackground::create([
                    'background_type' => $type,
                    'asset_version' => ((int) $typedBackgrounds->max('asset_version')) + 1,
                    'source_kind' => self::OFFICIAL_SOURCE_KIND,
                    'original_file_name' => $type === CertificateBackground::TYPE_CERTIFICATE
                        ? 'RES Certificate official background.jpeg'
                        : 'RES Review Worksheet official background.png',
                    'stored_file_path' => $path,
                    'mime_type' => $expectedMime,
                    'file_size_bytes' => filesize($resource),
                    'sha256' => $hash,
                    'width_pixels' => $dimensions[0],
                    'height_pixels' => $dimensions[1],
                    'page_count' => 1,
                    'is_active' => ! $typedBackgrounds->contains('is_active', true),
                    'activated_at' => $typedBackgrounds->contains('is_active', true) ? null : now(),
                ]);
            } elseif ($official->stored_file_path !== $path || ! hash_equals($official->sha256, $hash)) {
                $official->update([
                    'stored_file_path' => $path,
                    'mime_type' => $expectedMime,
                    'file_size_bytes' => filesize($resource),
                    'sha256' => $hash,
                    'width_pixels' => $dimensions[0],
                    'height_pixels' => $dimensions[1],
                    'page_count' => 1,
                ]);
            }

            $hasIntactActive = $typedBackgrounds
                ->where('is_active', true)
                ->contains(fn (CertificateBackground $background): bool => $this->isIntact($background));
            if (! $hasIntactActive) {
                $activatedAt = now();
                CertificateBackground::query()
                    ->where('background_type', $type)
                    ->where('is_active', true)
                    ->whereKeyNot($official->id)
                    ->update(['is_active' => false, 'superseded_at' => $activatedAt]);
                $official->update(['is_active' => true, 'activated_at' => $activatedAt, 'superseded_at' => null]);
            }

            return $official->refresh();
        }, 3);
    }

    public function uploadAndActivate(
        User $actor,
        UploadedFile $file,
        string $type = CertificateBackground::TYPE_CERTIFICATE,
    ): CertificateBackground {
        $this->authorize($actor);
        $this->assertType($type);
        $metadata = $this->validateAsset($file);
        $hash = hash_file('sha256', (string) $file->getRealPath());
        if (! is_string($hash)) {
            throw ValidationException::withMessages([
                'background' => 'The background could not be verified securely.',
            ])->errorBag('certificateBackground');
        }

        $extension = match ($metadata['mime_type']) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };
        $path = $file->storeAs("managed-backgrounds/{$type}", Str::uuid().'.'.$extension, 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'background' => 'The background could not be stored securely.',
            ])->errorBag('certificateBackground');
        }

        try {
            return DB::transaction(function () use ($actor, $file, $metadata, $hash, $path, $type): CertificateBackground {
                $backgrounds = CertificateBackground::query()->orderBy('id')->lockForUpdate()->get();
                $typedBackgrounds = $backgrounds->where('background_type', $type);
                $matching = $backgrounds->first(
                    fn (CertificateBackground $background): bool => hash_equals($background->sha256, $hash)
                        && $background->background_type === $type
                        && $this->isIntact($background),
                );

                if ($matching) {
                    Storage::disk('local')->delete($path);
                    $background = $matching;
                } else {
                    $background = CertificateBackground::create([
                        'background_type' => $type,
                        'asset_version' => ((int) $typedBackgrounds->max('asset_version')) + 1,
                        'source_kind' => 'res_uploaded',
                        'original_file_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                        'stored_file_path' => $path,
                        'mime_type' => $metadata['mime_type'],
                        'file_size_bytes' => $file->getSize(),
                        'sha256' => $hash,
                        'width_pixels' => $metadata['width_pixels'],
                        'height_pixels' => $metadata['height_pixels'],
                        'page_count' => $metadata['page_count'],
                        'uploaded_by_user_id' => $actor->id,
                    ]);
                }

                $activatedAt = now();
                CertificateBackground::query()
                    ->where('background_type', $type)
                    ->where('is_active', true)
                    ->whereKeyNot($background->id)
                    ->update(['is_active' => false, 'superseded_at' => $activatedAt]);
                $background->update([
                    'is_active' => true,
                    'activated_at' => $activatedAt,
                    'superseded_at' => null,
                ]);

                $this->auditLog->record($actor, $this->auditAction($type), $background, [
                    'background_id' => $background->id,
                    'background_type' => $type,
                    'asset_version' => $background->asset_version,
                    'source_kind' => $background->source_kind,
                    'mime_type' => $background->mime_type,
                    'sha256' => $background->sha256,
                    'result' => 'active_for_future_outputs',
                ]);

                return $background->refresh();
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function activate(User $actor, CertificateBackground $background): CertificateBackground
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $background): CertificateBackground {
            CertificateBackground::query()->orderBy('id')->lockForUpdate()->get();
            $locked = CertificateBackground::query()->whereKey($background->id)->lockForUpdate()->firstOrFail();
            $this->assertType($locked->background_type);
            abort_unless($this->isIntact($locked), 404);

            if ($locked->is_active) {
                return $locked;
            }

            $activatedAt = now();
            CertificateBackground::query()
                ->where('background_type', $locked->background_type)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'superseded_at' => $activatedAt,
                ]);
            $locked->update([
                'is_active' => true,
                'activated_at' => $activatedAt,
                'superseded_at' => null,
            ]);
            $this->auditLog->record($actor, $this->auditAction($locked->background_type), $locked, [
                'background_id' => $locked->id,
                'background_type' => $locked->background_type,
                'asset_version' => $locked->asset_version,
                'source_kind' => $locked->source_kind,
                'sha256' => $locked->sha256,
                'result' => 'active_for_future_outputs',
            ]);

            return $locked->refresh();
        }, 3);
    }

    public function resetToOfficial(
        User $actor,
        string $type = CertificateBackground::TYPE_CERTIFICATE,
    ): CertificateBackground {
        $this->authorize($actor);
        $official = $this->ensureOfficialDefault($type);

        return $this->activate($actor, $official);
    }

    public function isIntact(CertificateBackground $background): bool
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($background->stored_file_path)) {
            return false;
        }

        $hash = hash_file('sha256', $disk->path($background->stored_file_path));

        return is_string($hash) && hash_equals($background->sha256, $hash);
    }

    /** @return array{mime_type: string, width_pixels: int|null, height_pixels: int|null, page_count: int} */
    private function validateAsset(UploadedFile $file): array
    {
        $mimeType = (string) $file->getMimeType();
        if (! in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages([
                'background' => 'Upload a valid single-page PDF, JPEG, or PNG background.',
            ])->errorBag('certificateBackground');
        }

        if ($mimeType === 'application/pdf') {
            try {
                $pdf = new Fpdi('P', 'mm', 'A4');
                $pageCount = $pdf->setSourceFile((string) $file->getRealPath());
                if ($pageCount !== 1) {
                    throw new \RuntimeException('page_count');
                }
                $template = $pdf->importPage(1);
                $size = $pdf->getTemplateSize($template);
                $this->assertA4Compatible((float) $size['width'], (float) $size['height']);
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'background' => 'The PDF must decode successfully as one portrait A4-compatible page.',
                ])->errorBag('certificateBackground');
            }

            return [
                'mime_type' => $mimeType,
                'width_pixels' => null,
                'height_pixels' => null,
                'page_count' => 1,
            ];
        }

        $dimensions = @getimagesize((string) $file->getRealPath());
        if (! is_array($dimensions) || ($dimensions['mime'] ?? null) !== $mimeType) {
            throw ValidationException::withMessages([
                'background' => 'The image could not be decoded or its detected type did not match.',
            ])->errorBag('certificateBackground');
        }
        if ($dimensions[0] < 596 || $dimensions[1] < 842) {
            throw ValidationException::withMessages([
                'background' => 'Use a portrait background of at least 596 by 842 pixels.',
            ])->errorBag('certificateBackground');
        }
        $this->assertA4Compatible((float) $dimensions[0], (float) $dimensions[1]);

        return [
            'mime_type' => $mimeType,
            'width_pixels' => $dimensions[0],
            'height_pixels' => $dimensions[1],
            'page_count' => 1,
        ];
    }

    private function assertA4Compatible(float $width, float $height): void
    {
        if ($width >= $height || abs(($width / $height) - (210 / 297)) > 0.035) {
            throw new \RuntimeException('incompatible_page_ratio');
        }
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->role === UserRole::ResLead, 403);
    }

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, [
            CertificateBackground::TYPE_CERTIFICATE,
            CertificateBackground::TYPE_REVIEW_WORKSHEET,
        ], true), 404);
    }

    private function auditAction(string $type): string
    {
        return $type === CertificateBackground::TYPE_CERTIFICATE
            ? 'certificate.background_activated'
            : 'review_worksheet.background_activated';
    }
}
