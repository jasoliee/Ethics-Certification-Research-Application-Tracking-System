<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validates the extension, server-detected MIME type, and file signature together.
 */
class SafeApplicationDocumentUpload implements ValidationRule
{
    public const MAX_FILE_KILOBYTES = 102400;

    /** @var array<string, array{storage_extension: string, client_extensions: list<string>}> */
    public const MIME_TYPES = [
        'application/pdf' => [
            'storage_extension' => 'pdf',
            'client_extensions' => ['pdf'],
        ],
        'image/jpeg' => [
            'storage_extension' => 'jpg',
            'client_extensions' => ['jpg', 'jpeg'],
        ],
        'image/png' => [
            'storage_extension' => 'png',
            'client_extensions' => ['png'],
        ],
        'image/gif' => [
            'storage_extension' => 'gif',
            'client_extensions' => ['gif'],
        ],
        'image/webp' => [
            'storage_extension' => 'webp',
            'client_extensions' => ['webp'],
        ],
    ];

    public const FAILURE_MESSAGE = 'Upload a valid PDF, JPG, JPEG, PNG, GIF, or WebP file whose extension matches its verified content.';

    /**
     * @return array{mime_type?: string, storage_extension?: string, error?: string}
     */
    public static function inspect(UploadedFile $file): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_FILE_KILOBYTES * 1024) {
            return ['error' => self::FAILURE_MESSAGE];
        }

        $mimeType = strtolower((string) $file->getMimeType());
        $configuration = self::MIME_TYPES[$mimeType] ?? null;
        $clientExtension = strtolower($file->getClientOriginalExtension());

        if (! $configuration || ! in_array($clientExtension, $configuration['client_extensions'], true)) {
            return ['error' => self::FAILURE_MESSAGE];
        }

        $path = $file->getRealPath();
        if (! is_string($path) || ! self::hasExpectedSignature($path, $mimeType)) {
            return ['error' => self::FAILURE_MESSAGE];
        }

        return [
            'mime_type' => $mimeType,
            'storage_extension' => $configuration['storage_extension'],
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail(self::FAILURE_MESSAGE);

            return;
        }

        $inspection = self::inspect($value);
        if (isset($inspection['error'])) {
            $fail($inspection['error']);
        }
    }

    private static function hasExpectedSignature(string $path, string $mimeType): bool
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            $header = fread($stream, 12);
        } finally {
            fclose($stream);
        }

        if (! is_string($header)) {
            return false;
        }

        return match ($mimeType) {
            'application/pdf' => str_starts_with($header, '%PDF-'),
            'image/jpeg' => str_starts_with($header, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($header, "\x89PNG\r\n\x1A\n"),
            'image/gif' => str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a'),
            'image/webp' => strlen($header) >= 12
                && substr($header, 0, 4) === 'RIFF'
                && substr($header, 8, 4) === 'WEBP',
            default => false,
        };
    }
}
