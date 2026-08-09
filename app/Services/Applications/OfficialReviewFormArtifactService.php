<?php

namespace App\Services\Applications;

use App\Enums\ReviewDecision;
use App\Enums\ReviewFormType;
use App\Exceptions\OfficialReviewFormGenerationException;
use App\Support\ReviewFormCatalog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Produces a flattened, first-party PDF from the official REMS source pages.
 */
class OfficialReviewFormArtifactService
{
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

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new OfficialReviewFormGenerationException('The official review-form source is unavailable.');
        }

        $templateHash = hash_file('sha256', $sourcePath);

        if (! is_string($templateHash) || ! hash_equals($manifest['sha256'], strtolower($templateHash))) {
            throw new OfficialReviewFormGenerationException('The official review-form source failed integrity verification.');
        }

        try {
            $bytes = $this->render($sourcePath, $type, $payload, $context);
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
        ];
    }

    /** @param array<string, mixed> $payload
     * @param  array<string, mixed>  $context
     */
    private function render(
        string $sourcePath,
        ReviewFormType $type,
        array $payload,
        array $context,
    ): string {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pageCount = $pdf->setSourceFile($sourcePath);
        $manifest = ReviewFormCatalog::template($type);

        if ($pageCount < max($manifest['source_pages'])) {
            throw new OfficialReviewFormGenerationException('The official review-form source has missing pages.');
        }

        foreach ($manifest['source_pages'] as $sourcePage) {
            $templateId = $pdf->importPage($sourcePage);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
            $this->overlaySourcePage($pdf, $type, $sourcePage, $payload, $context);
        }

        $continuation = $this->continuationComments($type, $payload);

        if ($continuation !== []) {
            $this->addContinuationPages($pdf, $type, $continuation, $context);
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

        $this->writeFittedLine($pdf, $leftX, 72.2, 124.0, (string) ($context['research_title'] ?? ''), 7.5, 5.0);
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
                ? Str::limit($comment, 65, '... [continued]')
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
            $pdf->SetFont('Helvetica', 'B', 6);
            $this->writeSingleLine($pdf, 18.0, 141.0, 174.0, 'Complete explanation preserved on response continuation page.');
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
                    default => 217.3,
                }
            : 160.0;
            $this->writeWrapped($pdf, 16.0, $commentY, 176.0, 5.0, $comments, $isProtocol ? 3 : 4);
            $pdf->SetFont('Helvetica', 'B', 6);
            $this->writeSingleLine(
                $pdf,
                16.0,
                $isProtocol ? min($commentY + 17.0, 235.0) : 188.0,
                176.0,
                'Complete recommendation comments preserved on response continuation page.',
            );
        }

        $signatureY = $isProtocol ? 245.0 : 200.7;
        $pdf->SetFont('Helvetica', 'B', 6.5);
        $this->writeSingleLine(
            $pdf,
            25.0,
            $signatureY,
            110.0,
            'Electronically attested: '.(string) ($context['reviewer_name'] ?? ''),
        );
        $this->writeSingleLine($pdf, 145.0, $signatureY, 40.0, (string) ($context['review_date'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, comment: string}>
     */
    private function continuationComments(ReviewFormType $type, array $payload): array
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

        return $comments;
    }

    /** @param array<int, array{label: string, comment: string}> $comments
     * @param  array<string, mixed>  $context
     */
    private function addContinuationPages(Fpdi $pdf, ReviewFormType $type, array $comments, array $context): void
    {
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage('P', 'A4');
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetXY(18, 18);
        $pdf->SetTextColor(0, 73, 35);
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->Cell(0, 8, $this->pdfText($type->code().' RESPONSE CONTINUATION'), 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell(0, 5, $this->pdfText(
            'Confidential blind-review artifact - '.(string) ($context['application_code'] ?? '').' - page references preserve the finalized response record.',
        ));
        $pdf->Ln(3);

        foreach ($comments as $entry) {
            if ($pdf->GetY() > 255) {
                $pdf->AddPage('P', 'A4');
                $pdf->SetXY(18, 18);
            }

            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->MultiCell(174, 4.5, $this->pdfText($entry['label']));
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->MultiCell(174, 4.5, $this->pdfText('Comment: '.$entry['comment']));
            $pdf->Ln(2);
        }
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
}
