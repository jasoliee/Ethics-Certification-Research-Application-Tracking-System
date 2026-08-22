<?php

namespace App\Services\Settings;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WorksheetSignatorySettingsService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function update(User $actor, string $name, ?UploadedFile $signature): User
    {
        abort_unless($actor->hasReviewerAccess(), 403);
        $asset = $signature ? $this->storeSignature($signature) : null;

        try {
            return DB::transaction(function () use ($actor, $name, $asset): User {
                $updates = ['worksheet_signatory_name' => Str::squish($name)];
                if ($asset !== null) {
                    $updates += [
                        'worksheet_signature_path' => $asset['path'],
                        'worksheet_signature_sha256' => $asset['sha256'],
                        'worksheet_signature_width' => $asset['width'],
                        'worksheet_signature_height' => $asset['height'],
                        'worksheet_signature_uploaded_at' => now(),
                    ];
                }

                $actor->forceFill($updates)->save();
                $this->auditLog->record($actor, 'settings.worksheet_signatory_updated', $actor, [
                    'printed_name' => $updates['worksheet_signatory_name'],
                    'signature_replaced' => $asset !== null,
                    'signature_sha256' => $asset['sha256'] ?? $actor->worksheet_signature_sha256,
                    'result' => 'updated_for_future_worksheets',
                ]);

                return $actor->refresh();
            }, 3);
        } catch (Throwable $exception) {
            if ($asset !== null) {
                Storage::disk('local')->delete($asset['path']);
            }

            throw $exception;
        }
    }

    /** @return array{path: string, sha256: string, width: int, height: int} */
    private function storeSignature(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $bytes = is_string($realPath) ? file_get_contents($realPath) : false;
        $dimensions = is_string($realPath) ? @getimagesize($realPath) : false;
        if (! is_string($bytes)
            || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
            || ! is_array($dimensions)
            || ($dimensions['mime'] ?? null) !== 'image/png') {
            throw ValidationException::withMessages([
                'signature' => 'Upload a genuine PNG signature image.',
            ])->errorBag('worksheetSignatory');
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width < 80 || $height < 24 || $width > 2400 || $height > 1200) {
            throw ValidationException::withMessages([
                'signature' => 'Use a signature image between 80x24 and 2400x1200 pixels.',
            ])->errorBag('worksheetSignatory');
        }

        $path = $file->storeAs('settings/worksheet-signatures', Str::uuid().'.png', 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'signature' => 'The signature could not be stored privately.',
            ])->errorBag('worksheetSignatory');
        }

        return [
            'path' => $path,
            'sha256' => hash('sha256', $bytes),
            'width' => $width,
            'height' => $height,
        ];
    }
}
