<?php

namespace Tests\Unit;

use App\Models\ApplicationDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApplicationDocumentTest extends TestCase
{
    #[DataProvider('previewTypeProvider')]
    public function test_preview_behavior_uses_the_server_verified_mime_type(
        string $mimeType,
        string $previewKind,
        string $label,
        bool $supportsInlinePreview,
    ): void {
        $document = new ApplicationDocument(['mime_type' => $mimeType]);

        $this->assertSame($previewKind, $document->previewKind());
        $this->assertSame($label, $document->fileTypeLabel());
        $this->assertSame($supportsInlinePreview, $document->supportsInlinePreview());
    }

    /**
     * @return array<string, array{string, string, string, bool}>
     */
    public static function previewTypeProvider(): array
    {
        return [
            'PDF' => ['application/pdf', 'pdf', 'PDF document', true],
            'Word DOC' => ['application/msword', 'office', 'Microsoft Word document (.doc)', false],
            'Word DOCX' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'office', 'Microsoft Word document (.docx)', false],
            'Excel XLS' => ['application/vnd.ms-excel', 'office', 'Microsoft Excel workbook (.xls)', false],
            'Excel XLSX' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'office', 'Microsoft Excel workbook (.xlsx)', false],
            'JPEG' => ['image/jpeg', 'image', 'JPEG image', true],
            'PNG' => ['image/png', 'image', 'PNG image', true],
            'unknown' => ['application/octet-stream', 'download', 'Document', false],
        ];
    }
}
