<?php

namespace App\Services\Reports;

use App\Enums\ReviewType;
use App\Models\CertificateBackground;
use App\Services\Certificates\CertificateBackgroundService;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OperationalReportExportService
{
    public function __construct(private readonly CertificateBackgroundService $backgrounds) {}

    public function reportExcel(array $report, array $survey, string $scope): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Operational Report');
        $row = 1;
        $this->title($sheet, $row, 'ECRATS Research Ethics Unit Operational Report', $scope);
        $this->section($sheet, $row, 'Overall Summary', ['Metric', 'Count'], collect($report['summary'])->map(fn ($value, $key) => [Str::headline($key), $value])->values()->all());
        $this->section($sheet, $row, 'Applicant & Application Summary by Institute', ['Institute', 'Unique Applicants', 'Submitted', 'Not Yet Submitted', 'Failed', 'Claimed', 'Unclaimed'], collect($report['institute_summary'])->map(fn ($item) => array_values($item))->all());
        $this->section($sheet, $row, 'Review Classification Summary', ['Classification', 'Applications'], collect($report['classifications'])->map(fn ($item) => [$item['label'], $item['count']])->all());
        $this->section($sheet, $row, 'Adviser & Reviewer Summary', ['Institute', 'Research Advisers', 'Reviewer-enabled Advisers'], collect($report['adviser_reviewer_summary'])->map(fn ($item) => [$item['institute'], $item['advisers'], $item['reviewers']])->all());
        $this->section($sheet, $row, 'Reviewer Review Workload', ['Reviewer', 'Institute', 'Expedited', 'Full Board', 'Total', 'Completed', 'Pending', 'Overdue', 'Remaining Capacity'], collect($report['reviewer_workload'])->map(fn ($item) => [$item['reviewer']->name, $item['institute'], $item['expedited'], $item['full_board'], $item['total'], $item['completed'], $item['pending'], $item['overdue'], $item['remaining']])->all());
        $this->section($sheet, $row, 'Adviser Endorsement Workload', ['Adviser', 'Institute', 'Declared Expected', 'Applicants Received', 'Completed', 'Awaiting', 'Not Yet Received'], collect($report['adviser_workload'])->map(fn ($item) => [$item['adviser']->name, $item['institute'], $item['expected'], $item['received'], $item['endorsed'], $item['awaiting'], $item['not_received']])->all());
        $this->section($sheet, $row, 'Filtered Applications', ['Application Code', 'Research Title', 'Institute', 'Review Type', 'Workflow Status', 'Certificate Status', 'Submitted'], collect($report['applications'])->map(function ($item): array {
            $application = $item['application'];

            return [$application->application_code, $application->research_title, $application->institution, $application->review_type ? ReviewType::tryFrom((string) $application->review_type)?->label() : 'Not classified', $application->statusLabel(), $item['certificate_status'], $application->submitted_at?->format('M j, Y g:i A')];
        })->all());
        $this->section($sheet, $row, 'Applicant Certification', ['Applicant', 'Institutional ID', 'Institute', 'Application Code', 'Certificate Status', 'Released Date', 'Ageing'], collect($report['applicant_certification'])->map(fn ($item) => [$item['applicant']?->name, $item['applicant']?->institutional_identifier, $item['application']->institution, $item['application']->application_code, $item['certificate_status'], $item['released_at']?->format('M j, Y g:i A'), $item['ageing_days'] === null ? null : $item['ageing_days'].' days'])->all());

        $surveySheet = $spreadsheet->createSheet();
        $surveySheet->setTitle('Applicant Feedback');
        $this->writeSurveySheet($surveySheet, $survey, $scope);
        $this->finishWorkbook($spreadsheet);

        return $this->xlsxBytes($spreadsheet);
    }

    public function surveyExcel(array $survey, string $scope): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Applicant Feedback');
        $this->writeSurveySheet($spreadsheet->getActiveSheet(), $survey, $scope);
        $this->finishWorkbook($spreadsheet);

        return $this->xlsxBytes($spreadsheet);
    }

    public function reportPdf(array $report, string $scope): string
    {
        $pdf = $this->pdf('P');
        $pdf->reportTitle('ECRATS Research Ethics Unit Operational Report', $scope, 'Generated: '.now()->format('M j, Y g:i A'));
        $pdf->sectionTitle('Overall Summary');
        $pdf->table(['Metric', 'Count'], collect($report['summary'])->map(fn ($value, $key) => [Str::headline($key), $value])->values()->all(), [140, 45], ['L', 'C']);
        $pdf->sectionTitle('Applicant & Application Summary by Institute');
        $pdf->table(['Institute', 'Applicants', 'Submitted', 'Not Submitted', 'Failed', 'Claimed', 'Unclaimed'], collect($report['institute_summary'])->map(fn ($item) => [$item['institute'], $item['unique_applicants'], $item['submitted'], $item['not_submitted'], $item['failed'], $item['claimed'], $item['unclaimed']])->all(), [64, 20, 20, 22, 17, 21, 21], ['L', 'C', 'C', 'C', 'C', 'C', 'C']);
        $pdf->sectionTitle('Review Classification Summary');
        $pdf->table(['Classification', 'Applications'], collect($report['classifications'])->map(fn ($item) => [$item['label'], $item['count']])->all(), [140, 45], ['L', 'C']);
        $pdf->sectionTitle('Adviser & Reviewer Summary');
        $pdf->table(['Institute', 'Research Advisers', 'Reviewer-enabled Advisers'], collect($report['adviser_reviewer_summary'])->map(fn ($item) => [$item['institute'], $item['advisers'], $item['reviewers']])->all(), [89, 45, 51], ['L', 'C', 'C']);
        $pdf->sectionTitle('Reviewer Review Workload');
        $pdf->table(['Reviewer', 'Institute', 'Exp.', 'Full', 'Total', 'Done', 'Pending', 'Overdue', 'Capacity'], collect($report['reviewer_workload'])->map(fn ($item) => [$item['reviewer']->name, $item['institute'], $item['expedited'], $item['full_board'], $item['total'], $item['completed'], $item['pending'], $item['overdue'], $item['remaining']])->all(), [30, 47, 14, 14, 14, 14, 18, 18, 16], ['L', 'L', 'C', 'C', 'C', 'C', 'C', 'C', 'C']);
        $pdf->sectionTitle('Adviser Endorsement Workload');
        $pdf->table(['Adviser', 'Institute', 'Expected', 'Received', 'Completed', 'Awaiting', 'Not Received'], collect($report['adviser_workload'])->map(fn ($item) => [$item['adviser']->name, $item['institute'], $item['expected'], $item['received'], $item['endorsed'], $item['awaiting'], $item['not_received']])->all(), [28, 57, 20, 20, 20, 20, 20], ['L', 'L', 'C', 'C', 'C', 'C', 'C']);
        $pdf->sectionTitle('Filtered Applications');
        $pdf->table(['Application Code', 'Research Title', 'Institute', 'Review Type', 'Status', 'Certificate', 'Submitted'], collect($report['applications'])->map(function ($item): array {
            $application = $item['application'];

            return [$application->application_code, $application->research_title, $application->institution, $application->review_type ? ReviewType::tryFrom((string) $application->review_type)?->label() : 'Not classified', $application->statusLabel(), $item['certificate_status'], $application->submitted_at?->format('M j, Y')];
        })->all(), [26, 43, 36, 20, 25, 20, 15]);
        $pdf->sectionTitle('Applicant Certification');
        $pdf->table(['Applicant', 'Institutional ID', 'Institute', 'Application Code', 'Status', 'Released', 'Ageing'], collect($report['applicant_certification'])->map(fn ($item) => [$item['applicant']?->name, $item['applicant']?->institutional_identifier, $item['application']->institution, $item['application']->application_code, $item['certificate_status'], $item['released_at']?->format('M j, Y g:i A'), $item['ageing_days'] === null ? '-' : $item['ageing_days'].' days'])->all(), [30, 23, 40, 28, 20, 24, 20], ['L', 'L', 'L', 'L', 'C', 'C', 'C']);

        return $pdf->Output('S');
    }

    public function surveyPdf(array $survey, string $scope): string
    {
        $pdf = $this->pdf('P');
        $pdf->reportTitle('ECRATS Anonymous Applicant Feedback Report', $scope, 'Generated: '.now()->format('M j, Y g:i A'));
        $pdf->sectionTitle('Summary');
        $pdf->table(['Metric', 'Value'], [['Current-questionnaire responses', $survey['response_count']], ['Overall average', $survey['overall_average'] === null ? 'No data' : number_format($survey['overall_average'], 2).' / 5']], [140, 45], ['L', 'C']);
        foreach ($survey['sections'] as $section) {
            $pdf->sectionTitle($section['title'].' - '.($section['average'] === null ? 'No data' : number_format($section['average'], 2).' / 5'));
            $pdf->table(['Evaluation Statement', 'Responses', 'Average'], collect($section['questions'])->map(fn ($question) => [$question['label'], $question['response_count'], $question['average'] === null ? '-' : number_format($question['average'], 2).' / 5'])->values()->all(), [125, 28, 32], ['L', 'C', 'C']);
        }

        return $pdf->Output('S');
    }

    private function pdf(string $orientation): ReportPdfDocument
    {
        $pdf = new ReportPdfDocument($orientation, 'mm', 'A4');
        $portrait = strtoupper($orientation) === 'P';
        $pdf->SetMargins(10, $portrait ? 52 : 38, 10);
        $pdf->SetAutoPageBreak(true, $portrait ? 40 : 30);
        $pdf->useBackground($this->backgrounds->active(CertificateBackground::TYPE_REVIEW_WORKSHEET));
        $pdf->AddPage();

        return $pdf;
    }

    private function writeSurveySheet(Worksheet $sheet, array $survey, string $scope): void
    {
        $row = 1;
        $this->title($sheet, $row, 'ECRATS Anonymous Applicant Feedback Report', $scope);
        $this->section($sheet, $row, 'Summary', ['Metric', 'Value'], [['Current-questionnaire responses', $survey['response_count']], ['Overall Average', $survey['overall_average'] === null ? 'No data' : $survey['overall_average'].' / 5']]);
        foreach ($survey['sections'] as $section) {
            $this->section($sheet, $row, $section['title'], ['Evaluation Statement', 'Responses', 'Average'], collect($section['questions'])->map(fn ($question) => [$question['label'], $question['response_count'], $question['average'] === null ? null : $question['average'].' / 5'])->values()->all());
        }
    }

    private function title(Worksheet $sheet, int &$row, string $title, string $scope): void
    {
        $sheet->setCellValue('A'.$row, $title);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF087241');
        $row++;
        $sheet->setCellValue('A'.$row++, $scope);
        $sheet->setCellValue('A'.$row++, 'Generated: '.now()->format('M j, Y g:i A'));
        $row++;
    }

    /** @param list<string> $headers @param list<list<mixed>> $rows */
    private function section(Worksheet $sheet, int &$row, string $title, array $headers, array $rows): void
    {
        $sheet->setCellValue('A'.$row, $title);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF075F38');
        $row++;
        $headerRow = $row;
        foreach ($headers as $column => $header) {
            $sheet->setCellValue([$column + 1, $row], $header);
        }
        $row++;
        foreach ($rows as $values) {
            foreach ($values as $column => $value) {
                if (is_string($value) && preg_match('/^[=+\-@]/', $value) === 1) {
                    $sheet->setCellValueExplicit([$column + 1, $row], $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue([$column + 1, $row], $value);
                }
            }
            $row++;
        }
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = max($headerRow, $row - 1);
        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF075F38']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F4EE']],
        ]);
        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFB7C7BE');
        $centeredTextHeaders = ['Review Type', 'Certificate Status', 'Submitted', 'Released Date', 'Ageing'];
        foreach ($headers as $column => $header) {
            $values = collect($rows)
                ->map(fn (array $values) => $values[$column] ?? null)
                ->filter(fn (mixed $value): bool => $value !== null && $value !== '');
            $isNumericColumn = $values->isNotEmpty()
                && $values->every(fn (mixed $value): bool => is_int($value) || is_float($value));

            if (! $isNumericColumn && ! in_array($header, $centeredTextHeaders, true)) {
                continue;
            }

            $columnName = Coordinate::stringFromColumnIndex($column + 1);
            $sheet->getStyle($columnName.$headerRow.':'.$columnName.$lastRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $row += 2;
    }

    private function finishWorkbook(Spreadsheet $spreadsheet): void
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $dimension = $sheet->calculateWorksheetDimension();
            $sheet->getStyle($dimension)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle($dimension)->getFont()->setName('Arial')->setSize(10);
            $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $sheet->getColumnDimensionByColumn($column)->setWidth($column === 1 ? 34 : 22);
            }
            $sheet->freezePane('A5');
            $sheet->setAutoFilter('A5:'.$sheet->getHighestColumn().'5');
        }
        $spreadsheet->setActiveSheetIndex(0);
    }

    private function xlsxBytes(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ecrats-report-');
        if (! is_string($path)) {
            throw new \RuntimeException('Unable to prepare the report workbook.');
        }

        try {
            (new Xlsx($spreadsheet))->save($path);
            $bytes = file_get_contents($path);
            if (! is_string($bytes)) {
                throw new \RuntimeException('Unable to read the report workbook.');
            }

            return $bytes;
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($path);
        }
    }
}
