<?php

namespace App\Services\Reports;

use App\Models\CertificateBackground;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class ReportPdfDocument extends Fpdi
{
    /** @var array<int, array<string, mixed>> */
    protected array $extGStates = [];

    private mixed $backgroundTemplate = null;

    public function useBackground(CertificateBackground $background): void
    {
        $path = Storage::disk('local')->path($background->stored_file_path);
        if ($background->mime_type === 'application/pdf') {
            $this->setSourceFile($path);
            $this->backgroundTemplate = $this->importPage(1);

            return;
        }

        // Reusing a transparent PNG directly through FPDF can omit opaque image
        // regions on alternating pages. Flatten the complete image once into a
        // one-page PDF and reuse that imported page as an identical template.
        $backgroundPdf = new Fpdi('P', 'mm', 'A4');
        $backgroundPdf->SetAutoPageBreak(false);
        $backgroundPdf->AddPage();
        $backgroundPdf->Image(
            $path,
            0,
            0,
            210,
            297,
            $background->mime_type === 'image/png' ? 'PNG' : 'JPEG',
        );
        $backgroundBytes = $backgroundPdf->Output('S');
        $this->setSourceFile(StreamReader::createByString($backgroundBytes));
        $this->backgroundTemplate = $this->importPage(1);
    }

    public function Header(): void
    {
        if ($this->backgroundTemplate !== null) {
            $this->useTemplate($this->backgroundTemplate, 0, 0, $this->GetPageWidth(), $this->GetPageHeight());
        }

        $this->SetY($this->tMargin);
    }

    public function reportTitle(string $title, string $scope, string $generated): void
    {
        $this->SetTextColor(8, 114, 65);
        $this->SetFont('Arial', 'B', 16);
        $this->MultiCell(0, 7, $this->pdfText($title));
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, $this->pdfText($scope), 0, 1);
        $this->SetTextColor(82, 96, 113);
        $this->SetFont('Arial', '', 8);
        $this->MultiCell(0, 4, $this->pdfText($generated));
        $this->SetDrawColor(8, 114, 65);
        $this->Line($this->lMargin, $this->GetY() + 1, $this->GetPageWidth() - $this->rMargin, $this->GetY() + 1);
        $this->Ln(4);
    }

    public function sectionTitle(string $title): void
    {
        // Keep the heading with at least the table header that follows it. The
        // page-break trigger accounts for the branded footer margin, whereas
        // the physical page height does not.
        if ($this->GetY() > $this->PageBreakTrigger - 12) {
            $this->AddPage();
        }
        $this->SetTextColor(7, 95, 56);
        $this->SetFont('Arial', 'B', 10);
        $this->MultiCell(0, 6, $this->pdfText($title));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     * @param  list<float>  $widths
     * @param  list<string>  $alignments
     */
    public function table(array $headers, array $rows, array $widths, array $alignments = []): void
    {
        $this->tableRow($headers, $widths, $alignments, true);
        foreach ($rows as $row) {
            $this->tableRow($row, $widths, $alignments, false, $headers);
        }
        $this->Ln(3);
    }

    /** @param list<mixed> $cells @param list<float> $widths @param list<string> $alignments */
    private function tableRow(
        array $cells,
        array $widths,
        array $alignments,
        bool $header = false,
        array $repeatingHeaders = [],
    ): void {
        $lineHeight = 4.2;
        $header ? $this->SetFillColor(232, 244, 238) : $this->SetFillColor(255, 255, 255);
        $this->SetTextColor($header ? 7 : 23, $header ? 95 : 32, $header ? 56 : 43);
        $this->SetFont('Arial', $header ? 'B' : '', 7.2);
        $wrappedCells = [];
        foreach ($cells as $index => $cell) {
            $wrappedCells[] = $this->wrappedLines(
                $widths[$index],
                $this->pdfText((string) ($cell ?? '')),
            );
        }

        $firstChunk = true;
        while (max(array_map('count', $wrappedCells)) > 0) {
            $remainingLines = max(array_map('count', $wrappedCells));
            $fullHeight = max(6, ($remainingLines * $lineHeight) + 1.6);
            $availableHeight = $this->PageBreakTrigger - $this->GetY();

            // Keep ordinary rows together. Rows taller than one content area are
            // intentionally split into bounded chunks instead of letting MultiCell
            // create headerless or background-only pages.
            if ($firstChunk
                && $fullHeight > $availableHeight
                && $this->GetY() > $this->tMargin + 12) {
                $this->startTablePage($widths, $alignments, $header ? [] : $repeatingHeaders);
                $availableHeight = $this->PageBreakTrigger - $this->GetY();
            }

            $availableLines = (int) floor(max(0, $availableHeight - 1.6) / $lineHeight);
            if ($availableLines < 1 || max(6, ($availableLines * $lineHeight) + 1.6) > $availableHeight + 0.01) {
                $this->startTablePage($widths, $alignments, $header ? [] : $repeatingHeaders);

                continue;
            }

            $chunkLineCount = min($remainingLines, $availableLines);
            $height = max(6, ($chunkLineCount * $lineHeight) + 1.6);
            $x = $this->lMargin;
            $y = $this->GetY();

            foreach ($wrappedCells as $index => &$cellLines) {
                $width = $widths[$index];
                $chunk = array_splice($cellLines, 0, $chunkLineCount);
                $this->Rect($x, $y, $width, $height, 'DF');
                foreach ($chunk as $lineIndex => $line) {
                    $this->SetXY($x, $y + 0.8 + ($lineIndex * $lineHeight));
                    $this->Cell($width, $lineHeight, $line, 0, 0, $alignments[$index] ?? 'L');
                }
                $x += $width;
            }
            unset($cellLines);

            $this->SetXY($this->lMargin, $y + $height);
            $firstChunk = false;

            if (max(array_map('count', $wrappedCells)) > 0) {
                $this->startTablePage($widths, $alignments, $repeatingHeaders);
            }
        }
    }

    /** @param list<float> $widths @param list<string> $alignments @param list<mixed> $headers */
    private function startTablePage(array $widths, array $alignments, array $headers): void
    {
        $this->AddPage();
        if ($headers !== []) {
            $this->tableRow($headers, $widths, $alignments, true);
            $this->SetFillColor(255, 255, 255);
            $this->SetTextColor(23, 32, 43);
            $this->SetFont('Arial', '', 7.2);
        }
    }

    /** @return list<string> */
    private function wrappedLines(float $width, string $text): array
    {
        $characterWidths = $this->CurrentFont['cw'];
        $availableWidth = ($width - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $text = str_replace("\r", '', $text);
        $length = strlen($text);
        $separator = -1;
        $index = 0;
        $lineStart = 0;
        $lineWidth = 0;
        $lines = [];

        while ($index < $length) {
            $character = $text[$index];
            if ($character === "\n") {
                $lines[] = rtrim(substr($text, $lineStart, $index - $lineStart));
                $index++;
                $separator = -1;
                $lineStart = $index;
                $lineWidth = 0;

                continue;
            }
            if ($character === ' ') {
                $separator = $index;
            }
            $lineWidth += $characterWidths[$character] ?? 500;
            if ($lineWidth > $availableWidth) {
                if ($separator === -1) {
                    if ($index === $lineStart) {
                        $index++;
                    }
                    $lines[] = substr($text, $lineStart, $index - $lineStart);
                } else {
                    $lines[] = rtrim(substr($text, $lineStart, $separator - $lineStart));
                    $index = $separator + 1;
                }
                $separator = -1;
                $lineStart = $index;
                $lineWidth = 0;

                continue;
            }
            $index++;
        }

        if ($lineStart < $length) {
            $lines[] = rtrim(substr($text, $lineStart));
        }

        return $lines === [] ? [''] : $lines;
    }

    private function pdfText(string $value): string
    {
        $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);

        return is_string($converted) ? $converted : '';
    }

    private function setAlpha(float $alpha, string $blendMode = 'Normal'): void
    {
        $state = count($this->extGStates) + 1;
        $this->extGStates[$state] = ['ca' => $alpha, 'CA' => $alpha, 'BM' => '/'.$blendMode];
        $this->_out(sprintf('/GS%d gs', $state));
    }

    protected function _putresources(): void
    {
        foreach ($this->extGStates as $index => $state) {
            $this->_newobj();
            $this->extGStates[$index]['n'] = $this->n;
            $this->_put('<</Type /ExtGState');
            $this->_put(sprintf('/ca %.3F', $state['ca']));
            $this->_put(sprintf('/CA %.3F', $state['CA']));
            $this->_put('/BM '.$state['BM']);
            $this->_put('>>');
            $this->_put('endobj');
        }

        parent::_putresources();
    }

    protected function _putresourcedict(): void
    {
        parent::_putresourcedict();
        if ($this->extGStates === []) {
            return;
        }

        $this->_put('/ExtGState <<');
        foreach ($this->extGStates as $index => $state) {
            $this->_put('/GS'.$index.' '.$state['n'].' 0 R');
        }
        $this->_put('>>');
    }

    protected function _enddoc(): void
    {
        if ($this->extGStates !== [] && version_compare($this->PDFVersion, '1.4', '<')) {
            $this->PDFVersion = '1.4';
        }

        parent::_enddoc();
    }
}
