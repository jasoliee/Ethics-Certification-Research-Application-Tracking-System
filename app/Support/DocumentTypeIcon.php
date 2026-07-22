<?php

namespace App\Support;

class DocumentTypeIcon
{
    public static function fromMimeType(?string $mimeType): string
    {
        $mimeType = strtolower(trim((string) $mimeType));

        return match (true) {
            $mimeType === 'application/pdf' => 'file-pdf',
            in_array($mimeType, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ], true) => 'file-word',
            str_starts_with($mimeType, 'image/') => 'image',
            in_array($mimeType, [
                'text/csv',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ], true) => 'file-spreadsheet',
            default => 'file',
        };
    }
}
