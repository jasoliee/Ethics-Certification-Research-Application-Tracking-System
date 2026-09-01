<?php

namespace App\Services\Applications;

use App\Enums\ReviewDecision;
use App\Enums\ReviewFormType;
use App\Exceptions\OfficialReviewFormGenerationException;
use App\Models\CertificateBackground;
use App\Services\Certificates\CertificateBackgroundService;
use App\Support\ReviewFormCatalog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Produces a flattened, first-party PDF from the official REMS source pages.
 */
class OfficialReviewFormArtifactService
{
    public function __construct(
        private readonly CertificateBackgroundService $backgrounds,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, int|string>
     */
    public function renderAndStore(
        ReviewFormType $type,
        array $payload,
        array $context,
        int $artifactVersion,
    ): array {
        $sourcePath = ReviewFormCatalog::templatePath();
        $manifest = ReviewFormCatalog::template($type);
        $background = $this->backgrounds->active(CertificateBackground::TYPE_REVIEW_WORKSHEET);
        $backgroundPath = Storage::disk('local')->path($background->stored_file_path);

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new OfficialReviewFormGenerationException('The official review-form source is unavailable.');
        }

        if (! $this->backgrounds->isIntact($background)) {
            throw new OfficialReviewFormGenerationException('The active review worksheet background failed integrity verification.');
        }

        $templateHash = hash_file('sha256', $sourcePath);

        if (! is_string($templateHash) || ! hash_equals($manifest['sha256'], strtolower($templateHash))) {
            throw new OfficialReviewFormGenerationException('The official review-form source failed integrity verification.');
        }

        try {
            $bytes = $this->render($sourcePath, $backgroundPath, $background->mime_type, $type, $payload, $context);
        } catch (OfficialReviewFormGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OfficialReviewFormGenerationException(
                'The official review-form artifact could not be rendered.',
                previous: $exception,
            );
        }

        if (! str_starts_with($bytes, '%PDF-') || strlen($bytes) < 1000) {
            throw new OfficialReviewFormGenerationException('The generated review-form artifact is not a valid PDF.');
        }

        $assignmentId = (int) ($context['reviewer_assignment_id'] ?? 0);
        $path = 'review-form-artifacts/'.$assignmentId.'/'.Str::uuid().'.pdf';
        $applicationCode = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($context['application_code'] ?? 'application'));
        $fileName = $type->code().'-'.trim((string) $applicationCode, '-').'-v'.$artifactVersion.'.pdf';

        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new OfficialReviewFormGenerationException('The generated review-form artifact could not be stored securely.');
        }

        return [
            'stored_file_path' => $path,
            'original_file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'template_code' => $type->code(),
            'template_version' => $manifest['version'],
            'template_sha256' => $manifest['sha256'],
            'generator_version' => $manifest['generator_version'],
            'certificate_background_id' => $background->id,
            'background_sha256' => $background->sha256,
        ];
    }

    /** @param array<string, mixed> $payload
     * @param  array<string, mixed>  $context
     */
    private function render(
        string $sourcePath,
        string $backgroundPath,
        string $backgroundMimeType,
        ReviewFormType $type,
        array $payload,
        array $context,
    ): string {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $backgroundTemplateId = null;

        if ($backgroundMimeType === 'application/pdf') {
            $backgroundPageCount = $pdf->setSourceFile($backgroundPath);
            if ($backgroundPageCount !== 1) {
                throw new OfficialReviewFormGenerationException('The active review worksheet background must contain exactly one page.');
            }
            $backgroundTemplateId = $pdf->importPage(1);
        } else {
            $backgroundPdf = new Fpdi('P', 'mm', 'A4');
            $backgroundPdf->SetAutoPageBreak(false);
            $backgroundPdf->AddPage();
            $backgroundPdf->Image(
                $backgroundPath,
                0,
                0,
                210,
                297,
                $backgroundMimeType === 'image/png' ? 'PNG' : 'JPEG',
            );
            $pdf->setSourceFile(StreamReader::createByString($backgroundPdf->Output('S')));
            $backgroundTemplateId = $pdf->importPage(1);
        }

        $pageCount = $pdf->setSourceFile($sourcePath);
        $manifest = ReviewFormCatalog::template($type);

        if ($pageCount < max($manifest['source_pages'])) {
            throw new OfficialReviewFormGenerationException('The official review-form source has missing pages.');
        }

        foreach ($manifest['source_pages'] as $sourcePage) {
            $templateId = $pdf->importPage($sourcePage);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            // The official source pages already contain their complete branded layout.
            // Layering the managed background beneath them duplicated the page-three
            // heading and made the title unreadable. The managed background remains
            // authoritative for generated supplemental pages.
            $pdf->useTemplate($templateId);
            $this->overlaySourcePage($pdf, $type, $sourcePage, $payload, $context);
        }

        $continuation = $this->continuationComments($type, $payload, $context);

        if ($continuation !== []) {
            $this->addContinuationPages(
                $pdf,
                $type,
                $continuation,
                $context,
                $backgroundPath,
                $backgroundMimeType,
                $backgroundTemplateId,
            );
        }

        $output = $pdf->Output('S');

        if (! is_string($output)) {
            throw new OfficialReviewFormGenerationException('The PDF renderer returned no artifact bytes.');
        }

        return $output;
    }

    /** @param array<string, mixed> $payload
     * @param  array<string, mixed>  $context
     */
    private function overlaySourcePage(
        Fpdi $pdf,
        ReviewFormType $type,
        int $sourcePage,
        array $payload,
        array $context,
    ): void {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);

        if ($sourcePage === ReviewFormCatalog::template($type)['source_pages'][0]) {
            $this->overlayContext($pdf, $type, $context);
        }

        $this->overlayAnswers($pdf, $type, $sourcePage, $payload);

        $lastPage = max(ReviewFormCatalog::template($type)['source_pages']);

        if ($sourcePage === $lastPage) {
            $this->overlayRecommendation($pdf, $type, $payload, $context);
        }

        if ($type === ReviewFormType::InformedConsent && $sourcePage === 7) {
            $this->overlayConsentGate($pdf, $payload);
        }
    }

    /** @param array<string, mixed> $context */
    private function overlayContext(Fpdi $pdf, ReviewFormType $type, array $context): void
    {
        $isProtocol = $type === ReviewFormType::Protocol;
        $leftX = $isProtocol ? 68.0 : 60.0;
        $leftWidth = $isProtocol ? 40.0 : 43.0;
        $rightX = $isProtocol ? 150.0 : 145.0;
        $rightWidth = $isProtocol ? 42.0 : 47.0;

        $pdf->SetFont('Helvetica', 'B', 5.25);
        $pdf->SetXY($leftX, 69.2);
        $pdf->MultiCell(124.0, 3.1, $this->pdfText(Str::limit(trim((string) ($context['research_title'] ?? '')), 260, '...')), 0, 'L');
        $this->writeFittedLine($pdf, $leftX, 82.2, $leftWidth, (string) ($context['application_code'] ?? ''), 6.5, 4.5);
        $this->writeFittedLine($pdf, $rightX, 82.2, $rightWidth, (string) ($context['review_classification'] ?? ''), 6.5, 4.5);

        if ($isProtocol) {
            $this->writeFittedLine($pdf, $leftX, 92.1, $leftWidth, 'WITHHELD - BLIND REVIEW', 6.0, 4.5);
            $this->writeFittedLine($pdf, $rightX, 92.1, $rightWidth, (string) ($context['institution'] ?? ''), 6.0, 4.0);
            $this->writeFittedLine($pdf, $leftX, 103.0, $leftWidth, (string) ($context['reviewer_name'] ?? ''), 6.5, 4.5);
            $this->writeFittedLine($pdf, $rightX, 103.0, $rightWidth, (string) ($context['received_date'] ?? ''), 6.5, 4.5);
        } else {
            $this->writeFittedLine($pdf, $leftX, 92.1, $leftWidth, 'WITHHELD - BLIND REVIEW', 6.0, 4.5);
            $this->writeFittedLine($pdf, $rightX, 92.1, $rightWidth, (string) ($context['institution'] ?? ''), 6.0, 4.0);
            $this->writeFittedLine($pdf, $leftX, 102.0, $leftWidth, (string) ($context['reviewer_name'] ?? ''), 6.5, 4.5);
            $this->writeFittedLine($pdf, $rightX, 102.0, $rightWidth, (string) ($context['primary_reviewer_label'] ?? 'Not designated'), 6.0, 4.0);
        }
    }

    /** @param array<string, mixed> $payload */
    private function overlayAnswers(Fpdi $pdf, ReviewFormType $type, int $sourcePage, array $payload): void
    {
        $responses = (array) ($payload['responses'] ?? []);
        $answerX = $type === ReviewFormType::Protocol
            ? ['no' => 146.5, 'yes' => 159.2, 'unable_to_assess' => 180.6]
            : ['no' => 148.0, 'yes' => 178.0];
        $pdf->SetFont('Helvetica', 'B', 9);

        foreach (ReviewFormCatalog::items($type) as $key => $item) {
            $response = (array) ($responses[$key] ?? []);
            $answer = $response['answer'] ?? null;

            if ($type === ReviewFormType::InformedConsent
                && ($payload['consent_required'] ?? null) === false
                && (int) $item['source_page'] === $sourcePage) {
                $pdf->SetXY(151.0, (float) $item['answer_y_mm'] - 1.0);
                $pdf->Cell(35, 4, 'N/A', 0, 0, 'C');

                continue;
            }

            if ((int) $item['source_page'] === $sourcePage && isset($answerX[$answer])) {
                $pdf->SetXY($answerX[$answer] - 2.5, (float) $item['answer_y_mm'] - 1.0);
                $pdf->Cell(5, 4, 'X', 0, 0, 'C');
            }

            if ($type !== ReviewFormType::Protocol
                || (int) ($item['comment_source_page'] ?? 0) !== $sourcePage
                || blank($response['comment'] ?? null)) {
                continue;
            }

            $pdf->SetFont('Helvetica', '', 6.5);
            $comment = (string) $response['comment'];
            $printedComment = mb_strlen($comment) > 70
                ? Str::limit($comment, 68, '...')
                : $comment;
            $this->writeSingleLine(
                $pdf,
                41.0,
                (float) $item['comment_y_mm'],
                90.0,
                $printedComment,
            );
            $pdf->SetFont('Helvetica', 'B', 9);
        }
    }

    /** @param array<string, mixed> $payload */
    private function overlayConsentGate(Fpdi $pdf, array $payload): void
    {
        $required = $payload['consent_required'] ?? null;
        $pdf->SetFont('Helvetica', 'B', 8);

        if ($required === true) {
            $this->writeSingleLine($pdf, 174.0, 120.0, 18.0, 'YES');
        } elseif ($required === false) {
            $this->writeSingleLine($pdf, 174.0, 120.0, 18.0, 'NO');
            $pdf->SetFont('Helvetica', '', 7);
            $this->writeWrapped(
                $pdf,
                18.0,
                130.0,
                174.0,
                4.8,
                (string) ($payload['consent_not_required_explanation'] ?? ''),
                3,
            );
        }
    }

    /** @param array<string, mixed> $payload
     * @param  array<string, mixed>  $context
     */
    private function overlayRecommendation(Fpdi $pdf, ReviewFormType $type, array $payload, array $context): void
    {
        $recommendation = (string) ($payload['recommendation'] ?? '');
        $isProtocol = $type === ReviewFormType::Protocol;
        $recommendationY = $isProtocol
            ? [
                ReviewDecision::Approved->value => 104.4,
                ReviewDecision::MinorRevision->value => 110.2,
                ReviewDecision::MajorRevision->value => 116.0,
                ReviewDecision::Disapproved->value => 121.8,
            ]
            : [
                ReviewDecision::Approved->value => 123.1,
                ReviewDecision::MinorRevision->value => 128.9,
                ReviewDecision::MajorRevision->value => 134.7,
                ReviewDecision::Disapproved->value => 140.5,
            ];

        if (isset($recommendationY[$recommendation])) {
            $pdf->SetFont('Helvetica', 'B', 9);
            $this->writeSingleLine($pdf, 22.0, $recommendationY[$recommendation], 5.0, 'X');
        }

        $comments = (string) ($payload['recommendation_comments'] ?? '');

        if (filled($comments)) {
            $pdf->SetFont('Helvetica', '', 7);
            $commentY = $isProtocol
                ? match ($recommendation) {
                    ReviewDecision::MinorRevision->value => 149.5,
                    ReviewDecision::MajorRevision->value => 183.4,
                    default => 207.0,
                }
            : 160.0;
            $this->writeWrapped($pdf, 16.0, $commentY, 176.0, 5.0, $comments, $isProtocol ? 3 : 4);
        }

        $signatureY = $isProtocol ? 244.0 : 201.0;
        $signatureTextY = $signatureY - 2.0;
        $signatureLineX = 25.4;
        $signatureLineWidth = 62.06;
        $signaturePath = $this->verifiedWorksheetSignature($context);
        if ($signaturePath !== null) {
            $sourceWidth = max(1, (int) ($context['worksheet_signature_width'] ?? 1));
            $sourceHeight = max(1, (int) ($context['worksheet_signature_height'] ?? 1));
            $signatureScale = min(52.0 / $sourceWidth, 12.0 / $sourceHeight);
            $signatureImageWidth = $sourceWidth * $signatureScale;
            $signatureImageHeight = $sourceHeight * $signatureScale;
            $signatureImageX = $signatureLineX + (($signatureLineWidth - $signatureImageWidth) / 2);
            $signatureImageY = $signatureTextY - $signatureImageHeight - 0.5;
            $pdf->Image(
                $signaturePath,
                $signatureImageX,
                $signatureImageY,
                $signatureImageWidth,
                $signatureImageHeight,
                'PNG',
            );
        }
        $this->writeFittedCenteredLine(
            $pdf,
            $signatureLineX,
            $signatureTextY,
            $signatureLineWidth,
            (string) ($context['worksheet_signatory_name'] ?? $context['reviewer_name'] ?? ''),
            11.0,
            8.0,
        );
        $this->writeFittedCenteredLine(
            $pdf,
            144.02,
            $signatureTextY,
            39.79,
            (string) ($context['review_date'] ?? ''),
            11.0,
            8.5,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<int, array{label: string, comment: string}>
     */
    private function continuationComments(ReviewFormType $type, array $payload, array $context): array
    {
        $responses = (array) ($payload['responses'] ?? []);
        $comments = [];

        foreach (ReviewFormCatalog::items($type) as $key => $item) {
            $comment = trim((string) data_get($responses, $key.'.comment', ''));

            if ($comment === '') {
                continue;
            }

            $number = $item['printed_number'] ?? null;
            $comments[] = [
                'label' => ($number ? $number.'. ' : '').(string) $item['text'],
                'comment' => $comment,
            ];
        }

        if (filled($payload['consent_not_required_explanation'] ?? null)) {
            $comments[] = [
                'label' => 'Informed-consent necessity gate',
                'comment' => (string) $payload['consent_not_required_explanation'],
            ];
        }

        if (filled($payload['recommendation_comments'] ?? null)) {
            $comments[] = [
                'label' => 'Recommendation comments',
                'comment' => (string) $payload['recommendation_comments'],
            ];
        }

        $finalReview = (array) ($context['final_review'] ?? []);

        if (filled($finalReview['decision_label'] ?? null)) {
            $comments[] = [
                'label' => 'Overall final review decision',
                'comment' => (string) $finalReview['decision_label'],
            ];
        }

        if (filled($finalReview['decision_comment'] ?? null)) {
            $comments[] = [
                'label' => 'Final decision comment',
                'comment' => (string) $finalReview['decision_comment'],
            ];
        }

        if (filled($finalReview['submitted_at_display'] ?? null)) {
            $comments[] = [
                'label' => 'Overall review submitted',
                'comment' => (string) $finalReview['submitted_at_display'],
            ];
        }

        foreach ((array) ($finalReview['assignment_comments'] ?? []) as $entry) {
            $comment = (array) $entry;
            $metadata = array_filter([
                filled($comment['scope_label'] ?? null) ? 'Scope: '.$comment['scope_label'] : null,
                filled($comment['reference'] ?? null) ? 'Reference: '.$comment['reference'] : null,
                filled($comment['status'] ?? null) ? 'Status: '.Str::headline((string) $comment['status']) : null,
                filled($comment['created_at_display'] ?? null) ? 'Recorded: '.$comment['created_at_display'] : null,
                filled($comment['updated_at_display'] ?? null) ? 'Last updated: '.$comment['updated_at_display'] : null,
            ]);
            $comments[] = [
                'label' => 'Review comment #'.(int) ($comment['id'] ?? 0)
                    .' - '.(string) ($comment['category_label'] ?? 'General Comment'),
                'comment' => trim((string) ($comment['body'] ?? ''))
                    .($metadata === [] ? '' : "\n".implode(' | ', $metadata)),
            ];
        }

        return $comments;
    }

    /** @param array<int, array{label: string, comment: string}> $comments
     * @param  array<string, mixed>  $context
     */
    private function addContinuationPages(
        Fpdi $pdf,
        ReviewFormType $type,
        array $comments,
        array $context,
        string $backgroundPath,
        string $backgroundMimeType,
        mixed $backgroundTemplateId,
    ): void {
        $pdf->SetAutoPageBreak(false);
        $this->startContinuationPage($pdf, $type, $context, $backgroundPath, $backgroundMimeType, $backgroundTemplateId);

        foreach ($comments as $entry) {
            $pdf->SetFont('Helvetica', 'B', 8);
            $labelLines = $this->wrappedPdfLines($pdf, 174, $entry['label']);
            $pdf->SetFont('Helvetica', '', 8);
            $commentLines = $this->wrappedPdfLines($pdf, 174, 'Comment: '.$entry['comment']);

            if ($pdf->GetY() + ((count($labelLines) + 1) * 4.5) + 2 > 248) {
                $this->startContinuationPage($pdf, $type, $context, $backgroundPath, $backgroundMimeType, $backgroundTemplateId);
            }

            $pdf->SetFont('Helvetica', 'B', 8);
            $this->writePdfLines($pdf, $labelLines, 4.5);

            while ($commentLines !== []) {
                $availableLines = (int) floor((248 - $pdf->GetY()) / 4.5);
                if ($availableLines < 1) {
                    $this->startContinuationPage($pdf, $type, $context, $backgroundPath, $backgroundMimeType, $backgroundTemplateId);
                    $pdf->SetFont('Helvetica', 'B', 8);
                    $this->writePdfLines($pdf, $this->wrappedPdfLines($pdf, 174, $entry['label'].' (continued)'), 4.5);
                    $pdf->SetFont('Helvetica', '', 8);
                    $availableLines = (int) floor((248 - $pdf->GetY()) / 4.5);
                }

                $pdf->SetFont('Helvetica', '', 8);
                $this->writePdfLines($pdf, array_splice($commentLines, 0, $availableLines), 4.5);
            }

            if ($pdf->GetY() + 2 <= 248) {
                $pdf->Ln(2);
            }
        }
    }

    /** @param array<string, mixed> $context */
    private function startContinuationPage(
        Fpdi $pdf,
        ReviewFormType $type,
        array $context,
        string $backgroundPath,
        string $backgroundMimeType,
        mixed $backgroundTemplateId,
    ): void {
        $pdf->AddPage('P', 'A4');
        $pdf->SetAutoPageBreak(false, 0);
        $this->applyBackground($pdf, $backgroundPath, $backgroundMimeType, $backgroundTemplateId);
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetXY(18, 58);
        $pdf->SetTextColor(0, 73, 35);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell(174, 7, $this->pdfText($type->code().' SUPPLEMENTAL REVIEW RECORD'), 0, 1, 'C');
        $pdf->SetX(18);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->Cell(174, 5, $this->pdfText('RESEARCH ETHICS UNIT'), 0, 1, 'C');
        $pdf->SetDrawColor(0, 111, 61);
        $pdf->Line(18, 73, 192, 73);
        $pdf->SetXY(18, 77);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell(174, 5, $this->pdfText(
            'Confidential blind-review artifact - '.(string) ($context['application_code'] ?? '').' - source pages retain the official form layout and this page preserves the complete submitted review record.',
        ));
        $pdf->Ln(3);
    }

    /** @return list<string> */
    private function wrappedPdfLines(Fpdi $pdf, float $width, string $text): array
    {
        $value = str_replace("\r", '', $this->pdfText($text));
        $lines = [];

        foreach (explode("\n", $value) as $paragraph) {
            if ($paragraph === '') {
                $lines[] = '';

                continue;
            }

            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line.' '.$word;
                if ($pdf->GetStringWidth($candidate) <= $width) {
                    $line = $candidate;

                    continue;
                }

                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }

                while ($word !== '' && $pdf->GetStringWidth($word) > $width) {
                    $segment = '';
                    while ($word !== '') {
                        $candidate = $segment.$word[0];
                        if ($segment !== '' && $pdf->GetStringWidth($candidate) > $width) {
                            break;
                        }
                        $segment = $candidate;
                        $word = substr($word, 1);
                    }
                    $lines[] = $segment;
                }

                $line = $word;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines === [] ? [''] : $lines;
    }

    /** @param list<string> $lines */
    private function writePdfLines(Fpdi $pdf, array $lines, float $lineHeight): void
    {
        foreach ($lines as $line) {
            $y = $pdf->GetY();
            $pdf->Text(18, $y + $lineHeight - 1, $line);
            $pdf->SetY($y + $lineHeight);
        }
    }

    private function applyBackground(
        Fpdi $pdf,
        string $backgroundPath,
        string $backgroundMimeType,
        mixed $backgroundTemplateId,
    ): void {
        if ($backgroundTemplateId === null) {
            throw new OfficialReviewFormGenerationException('The active review worksheet background could not be imported.');
        }

        $pdf->useTemplate($backgroundTemplateId, 0, 0, 210, 297);
    }

    private function writeSingleLine(Fpdi $pdf, float $x, float $y, float $width, string $text): void
    {
        $pdf->SetXY($x, $y);
        $pdf->Cell($width, 4, $this->pdfText(Str::limit(trim($text), 90, '...')), 0, 0, 'L', false);
    }

    private function writeFittedLine(
        Fpdi $pdf,
        float $x,
        float $y,
        float $width,
        string $text,
        float $maximumSize,
        float $minimumSize,
    ): void {
        $value = $this->pdfText(trim($text));
        $fontSize = $maximumSize;
        $pdf->SetFont('Helvetica', 'B', $fontSize);

        while ($fontSize > $minimumSize && $pdf->GetStringWidth($value) > $width) {
            $fontSize -= 0.25;
            $pdf->SetFont('Helvetica', 'B', $fontSize);
        }

        if ($pdf->GetStringWidth($value) > $width) {
            $suffix = '...';

            while (mb_strlen($value) > 1
                && $pdf->GetStringWidth($value.$suffix) > $width) {
                $value = mb_substr($value, 0, -1);
            }

            $value = rtrim($value).$suffix;
        }

        $pdf->SetXY($x, $y);
        $pdf->Cell($width, 4, $value, 0, 0, 'L', false);
    }

    private function writeFittedCenteredLine(
        Fpdi $pdf,
        float $x,
        float $y,
        float $width,
        string $text,
        float $maximumSize,
        float $minimumSize,
    ): void {
        $value = $this->pdfText(trim($text));
        $fontSize = $maximumSize;
        $pdf->SetFont('Helvetica', 'B', $fontSize);

        while ($fontSize > $minimumSize && $pdf->GetStringWidth($value) > $width) {
            $fontSize -= 0.25;
            $pdf->SetFont('Helvetica', 'B', $fontSize);
        }

        if ($pdf->GetStringWidth($value) > $width) {
            $suffix = '...';

            while (mb_strlen($value) > 1
                && $pdf->GetStringWidth($value.$suffix) > $width) {
                $value = mb_substr($value, 0, -1);
            }

            $value = rtrim($value).$suffix;
        }

        $pdf->SetXY($x, $y);
        $pdf->Cell($width, 4.5, $value, 0, 0, 'C', false);
    }

    private function writeWrapped(
        Fpdi $pdf,
        float $x,
        float $y,
        float $width,
        float $lineHeight,
        string $text,
        int $maxLines,
    ): void {
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($width, $lineHeight, $this->pdfText(Str::limit(trim($text), $maxLines * 115, '...')));
    }

    private function pdfText(string $value): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);

        return is_string($converted) ? $converted : Str::ascii($value);
    }

    /** @param array<string, mixed> $context */
    private function verifiedWorksheetSignature(array $context): ?string
    {
        $storedPath = trim((string) ($context['worksheet_signature_path'] ?? ''));
        if ($storedPath === '') {
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($storedPath)) {
            throw new OfficialReviewFormGenerationException('The configured Reviewer signature is unavailable.');
        }
        $path = $disk->path($storedPath);
        $hash = hash_file('sha256', $path);
        $dimensions = @getimagesize($path);
        if (! is_string($hash)
            || ! hash_equals((string) ($context['worksheet_signature_sha256'] ?? ''), $hash)
            || ! is_array($dimensions)
            || ($dimensions['mime'] ?? null) !== 'image/png'
            || (int) $dimensions[0] !== (int) ($context['worksheet_signature_width'] ?? 0)
            || (int) $dimensions[1] !== (int) ($context['worksheet_signature_height'] ?? 0)) {
            throw new OfficialReviewFormGenerationException('The configured Reviewer signature failed integrity verification.');
        }

        return $path;
    }
}
