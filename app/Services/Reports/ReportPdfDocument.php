<?php

namespace App\Services\Reports;

use App\Models\CertificateBackground;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class ReportPdfDocument extends Fpdi
{
    /** @var array<int, array<string, mixed>> */
    protected array $extGStates = [];

    private ?string $backgroundImage = null;

    private mixed $backgroundTemplate = null;

    public function useBackground(CertificateBackground $background): void
    {
        $path = Storage::disk('local')->path($background->stored_file_path);
        if ($background->mime_type === 'application/pdf') {
            $this->setSourceFile($path);
            $this->backgroundTemplate = $this->importPage(1);

            return;
        }

        $this->backgroundImage = $path;
    }

    public function Header(): void
    {
        if ($this->backgroundTemplate !== null) {
            $this->useTemplate($this->backgroundTemplate, 0, 0, $this->GetPageWidth(), $this->GetPageHeight());
        } elseif ($this->backgroundImage !== null) {
            $this->Image($this->backgroundImage, 0, 0, $this->GetPageWidth(), $this->GetPageHeight());
        }

        $this->setAlpha(0.9);
        $this->SetFillColor(255, 255, 255);
        $this->Rect(5, 5, $this->GetPageWidth() - 10, $this->GetPageHeight() - 10, 'F');
        $this->setAlpha(1);
        $this->SetY(10);
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
        if ($this->GetY() > $this->GetPageHeight() - 25) {
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
            $this->tableRow($row, $widths, $alignments);
        }
        $this->Ln(3);
    }

    /** @param list<mixed> $cells @param list<float> $widths @param list<string> $alignments */
    private function tableRow(array $cells, array $widths, array $alignments, bool $header = false): void
    {
        $lineHeight = 4.2;
        $lineCount = 1;
        foreach ($cells as $index => $cell) {
            $lineCount = max($lineCount, $this->numberOfLines($widths[$index], $this->pdfText((string) ($cell ?? ''))));
        }
        $height = max(6, $lineCount * $lineHeight);

        if ($this->GetY() + $height > $this->GetPageHeight() - 12) {
            $this->AddPage();
        }

        $header ? $this->SetFillColor(232, 244, 238) : $this->SetFillColor(255, 255, 255);
        $this->SetTextColor($header ? 7 : 23, $header ? 95 : 32, $header ? 56 : 43);
        $this->SetFont('Arial', $header ? 'B' : '', 7.2);
        $x = $this->GetX();
        $y = $this->GetY();

        foreach ($cells as $index => $cell) {
            $width = $widths[$index];
            $this->Rect($x, $y, $width, $height, 'DF');
            $this->SetXY($x, $y + 0.8);
            $this->MultiCell($width, $lineHeight, $this->pdfText((string) ($cell ?? '')), 0, $alignments[$index] ?? 'L');
            $x += $width;
        }

        $this->SetXY($this->lMargin, $y + $height);
    }

    private function numberOfLines(float $width, string $text): int
    {
        $characterWidths = $this->CurrentFont['cw'];
        $availableWidth = ($width - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $text = str_replace("\r", '', $text);
        $length = strlen($text);
        $separator = -1;
        $index = 0;
        $lineStart = 0;
        $lineWidth = 0;
        $lines = 1;

        while ($index < $length) {
            $character = $text[$index];
            if ($character === "\n") {
                $index++;
                $separator = -1;
                $lineStart = $index;
                $lineWidth = 0;
                $lines++;

                continue;
            }
            if ($character === ' ') {
                $separator = $index;
            }
            $lineWidth += $characterWidths[$character] ?? 500;
            if ($lineWidth > $availableWidth) {
                $index = $separator === -1
                    ? ($index === $lineStart ? $index + 1 : $index)
                    : $separator + 1;
                $separator = -1;
                $lineStart = $index;
                $lineWidth = 0;
                $lines++;

                continue;
            }
            $index++;
        }

        return $lines;
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
