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

class SignatorySettingsService
{
    public const OFFICIAL_SIGNATURE_RESOURCE = 'resources/certificates/res-signatory-signature.png';

    public function __construct(private readonly AuditLogService $auditLog) {}

    public function update(
        User $actor,
        string $printedName,
        ?UploadedFile $signature,
        ?string $validUntil = null,
        ?UploadedFile $qrImage = null,
    ): User
    {
        abort_unless($actor->can('manageCertificateSignatory', $actor), 403);
        $asset = $signature ? $this->storeValidatedSignature($signature) : null;
        $qrAsset = $qrImage ? $this->storeValidatedQr($qrImage) : null;

        try {
            return DB::transaction(function () use ($actor, $printedName, $validUntil, $asset, $qrAsset): User {
                $previousName = $actor->certificate_signatory_name;
                $updates = [
                    'certificate_signatory_name' => Str::squish($printedName),
                    'certificate_valid_until' => $validUntil ?: $actor->certificate_valid_until,
                ];

                if ($asset !== null) {
                    $updates += [
                        'certificate_signature_path' => $asset['path'],
                        'certificate_signature_sha256' => $asset['sha256'],
                        'certificate_signature_width' => $asset['width'],
                        'certificate_signature_height' => $asset['height'],
                        'certificate_signature_uploaded_at' => now(),
                    ];
                }
                if ($qrAsset !== null) {
                    $updates += [
                        'certificate_qr_path' => $qrAsset['path'],
                        'certificate_qr_sha256' => $qrAsset['sha256'],
                        'certificate_qr_width' => $qrAsset['width'],
                        'certificate_qr_height' => $qrAsset['height'],
                        'certificate_qr_uploaded_at' => now(),
                    ];
                }

                $actor->forceFill($updates)->save();
                $this->auditLog->record($actor, 'settings.certificate_signatory_updated', $actor, [
                    'previous_printed_name' => $previousName,
                    'printed_name' => $updates['certificate_signatory_name'],
                    'signature_replaced' => $asset !== null,
                    'signature_sha256' => $asset['sha256'] ?? $actor->certificate_signature_sha256,
                    'qr_replaced' => $qrAsset !== null,
                    'qr_sha256' => $qrAsset['sha256'] ?? $actor->certificate_qr_sha256,
                    'valid_until' => $validUntil ?: $actor->certificate_valid_until?->format('Y-m-d'),
                    'result' => 'updated_for_future_certificates',
                ]);

                return $actor->refresh();
            }, 3);
        } catch (Throwable $exception) {
            if ($asset !== null) {
                Storage::disk('local')->delete($asset['path']);
            }
            if ($qrAsset !== null) {
                Storage::disk('local')->delete($qrAsset['path']);
            }

            throw $exception;
        }
    }

    /** @return array{path: string, sha256: string, width: int, height: int} */
    private function storeValidatedQr(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $bytes = is_string($realPath) ? file_get_contents($realPath) : false;
        $dimensions = is_string($realPath) ? @getimagesize($realPath) : false;
        if (! is_string($bytes)
            || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
            || ! is_array($dimensions)
            || ($dimensions['mime'] ?? null) !== 'image/png') {
            throw ValidationException::withMessages([
                'qr_image' => 'Upload a genuine, decodable PNG QR image.',
            ])->errorBag('signatory');
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width < 64 || $height < 64 || $width > 2400 || $height > 2400) {
            throw ValidationException::withMessages([
                'qr_image' => 'Use a QR image between 64x64 and 2400x2400 pixels.',
            ])->errorBag('signatory');
        }

        $hash = hash('sha256', $bytes);
        $path = $file->storeAs('settings/certificate-qr', Str::uuid().'.png', 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'qr_image' => 'The verified QR image could not be stored privately.',
            ])->errorBag('signatory');
        }

        return ['path' => $path, 'sha256' => $hash, 'width' => $width, 'height' => $height];
    }

    /** @return array{path: string, sha256: string, width: int, height: int} */
    private function storeValidatedSignature(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $bytes = is_string($realPath) ? file_get_contents($realPath) : false;
        $dimensions = is_string($realPath) ? @getimagesize($realPath) : false;

        if (! is_string($bytes)
            || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
            || ! is_array($dimensions)
            || ($dimensions['mime'] ?? null) !== 'image/png') {
            throw ValidationException::withMessages([
                'signature' => 'Upload a genuine, decodable PNG signature image.',
            ])->errorBag('signatory');
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width < 64 || $height < 32 || $width > 2400 || $height > 1200) {
            throw ValidationException::withMessages([
                'signature' => 'Use a signature image between 64×32 and 2400×1200 pixels.',
            ])->errorBag('signatory');
        }

        if (! $this->hasTransparency($bytes)
            || (function_exists('imagecreatefrompng') && ! $this->containsTransparentPixel($realPath))) {
            throw ValidationException::withMessages([
                'signature' => 'The PNG must include transparency so no solid background is printed.',
            ])->errorBag('signatory');
        }

        $hash = hash('sha256', $bytes);
        $path = $file->storeAs('settings/res-signatures', Str::uuid().'.png', 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'signature' => 'The verified signature could not be stored privately.',
            ])->errorBag('signatory');
        }

        return ['path' => $path, 'sha256' => $hash, 'width' => $width, 'height' => $height];
    }

    private function hasTransparency(string $bytes): bool
    {
        $colorType = strlen($bytes) > 25 ? ord($bytes[25]) : -1;
        if (in_array($colorType, [4, 6], true)) {
            return true;
        }

        $offset = 8;
        $byteCount = strlen($bytes);
        while ($offset + 12 <= $byteCount) {
            $lengthData = unpack('Nlength', substr($bytes, $offset, 4));
            $length = (int) ($lengthData['length'] ?? -1);
            $type = substr($bytes, $offset + 4, 4);
            $nextOffset = $offset + 12 + $length;

            if ($length < 0 || $nextOffset > $byteCount) {
                return false;
            }
            if ($type === 'tRNS' && $length > 0) {
                return true;
            }
            if ($type === 'IEND') {
                return false;
            }

            $offset = $nextOffset;
        }

        return false;
    }

    private function containsTransparentPixel(string $path): bool
    {
        $image = @imagecreatefrompng($path);
        if ($image === false) {
            return false;
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            $trueColor = imageistruecolor($image);

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $color = imagecolorat($image, $x, $y);
                    $alpha = $trueColor
                        ? (($color >> 24) & 0x7F)
                        : (int) (imagecolorsforindex($image, $color)['alpha'] ?? 0);

                    if ($alpha > 0) {
                        return true;
                    }
                }
            }
        } finally {
            imagedestroy($image);
        }

        return false;
    }
}
