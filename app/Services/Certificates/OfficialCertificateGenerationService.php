<?php

namespace App\Services\Certificates;

use App\Enums\ApplicantType;
use App\Enums\ApplicationStatus;
use App\Exceptions\CertificateGenerationException;
use App\Models\Certificate;
use App\Models\CertificateBackground;
use App\Models\ResearchApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Throwable;

class OfficialCertificateGenerationService
{
    public const GENERATOR_VERSION = 'official-res-certificate-v2';

    public const TEMPLATE_VERSION = 'RES-CERTIFICATE-2026-03';

    public const OFFICIAL_TEMPLATE_SHA256 = '998e7a943c81a83afb13df162a85eb08007c4eb2aa1ea51fedfa9909cd5ff960';

    public const OFFICIAL_BACKGROUND_SHA256 = 'd7332a1bfbca1abd35434b9016008188537f137795fa01222296c103256a848f';

    public const OFFICIAL_SIGNATURE_SHA256 = 'bd83c53334d58e369e4010be3c2b4828c3529d974f2e2c26c8576369666f8ee3';

    /**
     * @return array{
     *     stored_file_path: string,
     *     original_file_name: string,
     *     mime_type: string,
     *     file_size_bytes: int,
     *     sha256: string,
     *     official_template_version: string,
     *     official_template_sha256: string,
     *     certificate_background_id: int,
     *     background_sha256: string,
     *     generator_version: string,
     *     generated_by_user_id: int,
     *     generated_at: mixed,
     *     released_by_user_id: int,
     *     released_at: mixed
     * }
     */
    public function renderAndStore(
        User $actor,
        ResearchApplication $application,
        Certificate $certificate,
        CertificateBackground $background,
        int $version,
        mixed $releasedAt,
        mixed $generatedAt = null,
        ?int $releasedByUserId = null,
    ): array {
        $generatedAt ??= $releasedAt;
        $releasedByUserId ??= $actor->id;
        $templatePath = base_path('context_files/RES CERTIFIACTE.pdf');
        $this->assertVerifiedResource($templatePath, self::OFFICIAL_TEMPLATE_SHA256, 'official_template_invalid');
        [$signaturePath, $signatoryName] = $this->signatory($actor);

        $disk = Storage::disk('local');
        if (! $disk->exists($background->stored_file_path)) {
            throw new CertificateGenerationException(
                'The active certificate background is unavailable.',
                'background_missing',
            );
        }
        $backgroundPath = $disk->path($background->stored_file_path);
        $actualBackgroundHash = hash_file('sha256', $backgroundPath);
        if (! is_string($actualBackgroundHash) || ! hash_equals($background->sha256, $actualBackgroundHash)) {
            throw new CertificateGenerationException(
                'The active certificate background failed integrity verification.',
                'background_integrity_failed',
            );
        }

        try {
            $pdf = new Fpdi('P', 'mm', 'A4');
            $pdf->SetAutoPageBreak(false);
            $this->applyBackground($pdf, $background, $backgroundPath);
            $this->drawCertificate($pdf, $application, $certificate, $signaturePath, $signatoryName, $generatedAt);
            $bytes = $pdf->Output('S');
        } catch (CertificateGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CertificateGenerationException(
                'The certificate could not be rendered from the verified official assets.',
                'render_failed',
            );
        }

        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-') || strlen($bytes) < 1000) {
            throw new CertificateGenerationException(
                'The generated certificate file was incomplete.',
                'invalid_pdf_output',
            );
        }

        $safeNumber = Str::slug($certificate->certificate_number, '-');
        $storedPath = "certificates/{$application->id}/{$safeNumber}-v{$version}-".Str::uuid().'.pdf';
        if (! $disk->put($storedPath, $bytes)) {
            throw new CertificateGenerationException(
                'The generated certificate could not be stored securely.',
                'private_storage_failed',
            );
        }

        $size = $disk->size($storedPath);
        $hash = hash('sha256', $bytes);
        if ($size !== strlen($bytes)) {
            $disk->delete($storedPath);
            throw new CertificateGenerationException(
                'The stored certificate failed its size verification.',
                'stored_size_mismatch',
            );
        }

        return [
            'stored_file_path' => $storedPath,
            'original_file_name' => "{$safeNumber}-certificate-v{$version}.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => $size,
            'sha256' => $hash,
            'official_template_version' => self::TEMPLATE_VERSION,
            'official_template_sha256' => self::OFFICIAL_TEMPLATE_SHA256,
            'certificate_background_id' => $background->id,
            'background_sha256' => $actualBackgroundHash,
            'generator_version' => self::GENERATOR_VERSION,
            'generated_by_user_id' => $actor->id,
            'generated_at' => $generatedAt,
            'released_by_user_id' => $releasedByUserId,
            'released_at' => $releasedAt,
        ];
    }

    private function applyBackground(
        Fpdi $pdf,
        CertificateBackground $background,
        string $backgroundPath,
    ): void {
        if ($background->mime_type === 'application/pdf') {
            $pageCount = $pdf->setSourceFile($backgroundPath);
            if ($pageCount !== 1) {
                throw new CertificateGenerationException('The active background has an invalid page count.', 'background_page_count');
            }
            $template = $pdf->importPage(1);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);

            return;
        }

        $pdf->AddPage('P', 'A4');
        $pdf->Image($backgroundPath, 0, 0, 210, 297);
    }

    private function drawCertificate(
        Fpdi $pdf,
        ResearchApplication $application,
        Certificate $certificate,
        string $signaturePath,
        string $signatoryName,
        mixed $issuedAt,
    ): void {
        $application->loadMissing([
            'applicant:id,name,first_name,middle_name,last_name,suffix,applicant_type,institution,department,program',
            'documents' => fn ($documents) => $documents
                ->where('is_current', true)
                ->with('requirement:id,name')
                ->orderBy('document_requirement_id'),
            'decisionReleases' => fn ($releases) => $releases->latest('released_at'),
        ]);

        $applicantName = Str::upper($application->applicant?->name ?: 'NAME NOT RECORDED');
        $applicantType = Str::upper(
            ApplicantType::tryFrom((string) $application->applicant_type)?->label() ?? 'Applicant',
        );
        $institution = Str::upper($application->institution ?: $application->applicant?->institution ?: 'INSTITUTION NOT RECORDED');
        $reviewType = $application->application_status === ApplicationStatus::Exempted
            ? 'Exempted'
            : (filled($application->review_type) ? Str::headline((string) $application->review_type) : 'Not recorded');
        $approvalDate = $application->decisionReleases->first()?->released_at ?? $issuedAt;
        $documentNames = $application->documents
            ->map(fn ($document): string => $document->requirement?->name ?: $document->original_file_name)
            ->filter()
            ->unique()
            ->implode(', ');
        $documentDates = $application->documents
            ->pluck('uploaded_at')
            ->filter()
            ->sort()
            ->last();
        $reviewedDocuments = $documentNames !== ''
            ? $documentNames.($documentDates ? ' ('.$documentDates->format('F j, Y').')' : '')
            : 'No document list was recorded';
        $issuedDate = CarbonImmutable::parse($issuedAt);
        $validity = 'From '.$issuedDate->format('F j, Y')
            .' through '.$issuedDate->addYearNoOverflow()->format('F j, Y').'.';

        $pdf->SetTextColor(0, 0, 0);
        $this->centeredText($pdf, 'Research Ethics Section', 43.8, 7.3, 10, 'I');
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetXY(145, 48.6);
        $pdf->Cell(50, 5, $this->encoded($certificate->certificate_number), 0, 0, 'R');

        $this->centeredText($pdf, 'CERTIFICATE OF ETHICAL CLEARANCE', 68.5, 9, 18, 'B');
        $this->centeredText($pdf, 'This is to certify that the research proposal entitled:', 84.2, 6, 10);
        $this->fitCenteredBlock($pdf, Str::upper($application->research_title), 94, 18, 13, 9, 'B');
        $this->centeredText($pdf, 'submitted by', 120.5, 6, 10);
        $this->fitCenteredBlock($pdf, $applicantName, 131, 12, 15, 10, 'BU');
        $this->fitCenteredBlock($pdf, "{$applicantType}, {$institution}", 141, 9, 10, 8, 'I');

        $committeeText = "has been reviewed and granted ethical clearance by the KLD Research Ethics Board.\n"
            ."The committee reviewed the following documents: {$reviewedDocuments}\n"
            ."Type of Review Conducted: {$reviewType}\n"
            .'Date of Approval: '.$approvalDate->format('F j, Y')."\n"
            ."Validity Period: {$validity}";
        $this->fitCenteredBlock($pdf, $committeeText, 152, 30, 9.5, 7.3);

        $this->fitCenteredBlock(
            $pdf,
            'This certificate is issued based on compliance with national and international ethical guidelines for the protection of human participants in research.',
            186,
            14,
            9,
            7.5,
        );
        $this->fitCenteredBlock(
            $pdf,
            'The researcher is required to submit post-approval reports, including progress reports, reports of protocol deviations or adverse events, requests for protocol amendments, and the final report upon study completion.',
            200,
            18,
            9,
            7.2,
        );
        $this->fitCenteredBlock(
            $pdf,
            'Issued this '.$issuedAt->format('jS').' of '.$issuedAt->format('F Y').' at Kolehiyo ng Lungsod ng Dasmarinas, Burol I, Dasmarinas, Cavite.',
            221,
            13,
            9,
            7.5,
        );

        $pdf->Image($signaturePath, 89, 240, 30, 0, 'PNG');
        $this->centeredText($pdf, Str::upper($signatoryName), 254.5, 6, 10, 'B');
        $this->centeredText($pdf, 'Coordinator, Research Ethics Section', 261, 6, 9, 'I');
    }

    private function centeredText(
        Fpdi $pdf,
        string $text,
        float $y,
        float $height,
        float $fontSize,
        string $style = '',
    ): void {
        $pdf->SetFont('Helvetica', $style, $fontSize);
        $pdf->SetXY(15, $y);
        $pdf->Cell(180, $height, $this->encoded($text), 0, 0, 'C');
    }

    private function fitCenteredBlock(
        Fpdi $pdf,
        string $text,
        float $y,
        float $maxHeight,
        float $maxFontSize,
        float $minFontSize,
        string $style = '',
    ): void {
        $encoded = $this->encoded($text);
        $selectedFont = $minFontSize;
        $selectedLines = [$encoded];

        for ($fontSize = $maxFontSize; $fontSize >= $minFontSize; $fontSize -= 0.5) {
            $pdf->SetFont('Helvetica', $style, $fontSize);
            $lines = $this->wrapLines($pdf, $encoded, 178);
            $lineHeight = max(3.4, $fontSize * 0.42);
            if ((count($lines) * $lineHeight) <= $maxHeight) {
                $selectedFont = $fontSize;
                $selectedLines = $lines;
                break;
            }
        }

        $lineHeight = max(3.4, $selectedFont * 0.42);
        $pdf->SetFont('Helvetica', $style, $selectedFont);
        $pdf->SetXY(16, $y);
        $pdf->MultiCell(178, $lineHeight, implode("\n", $selectedLines), 0, 'C');
    }

    /** @return array<int, string> */
    private function wrapLines(Fpdi $pdf, string $text, float $maxWidth): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [] as $paragraph) {
            $current = '';
            foreach (preg_split('/\s+/', trim($paragraph)) ?: [] as $word) {
                $candidate = $current === '' ? $word : $current.' '.$word;
                if ($current !== '' && $pdf->GetStringWidth($candidate) > $maxWidth) {
                    $lines[] = $current;
                    $current = $word;
                } else {
                    $current = $candidate;
                }
            }
            $lines[] = $current;
        }

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    private function encoded(string $value): string
    {
        $encoded = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);

        return is_string($encoded) ? $encoded : $value;
    }

    private function assertVerifiedResource(string $path, string $expectedHash, string $failureCode): void
    {
        $hash = is_readable($path) ? hash_file('sha256', $path) : false;
        if (! is_string($hash) || ! hash_equals($expectedHash, $hash)) {
            throw new CertificateGenerationException(
                'An official certificate resource failed integrity verification.',
                $failureCode,
            );
        }
    }

    /** @return array{0: string, 1: string} */
    private function signatory(User $actor): array
    {
        $name = Str::squish((string) ($actor->certificate_signatory_name ?: 'SARIAH R. VILLANUEVA'));

        if (filled($actor->certificate_signature_path)) {
            $disk = Storage::disk('local');
            if (! $disk->exists($actor->certificate_signature_path)) {
                throw new CertificateGenerationException(
                    'The configured RES signatory signature is unavailable.',
                    'configured_signature_missing',
                );
            }

            $path = $disk->path($actor->certificate_signature_path);
            $hash = hash_file('sha256', $path);
            $dimensions = @getimagesize($path);
            if (! is_string($hash)
                || ! hash_equals((string) $actor->certificate_signature_sha256, $hash)
                || ! is_array($dimensions)
                || ($dimensions['mime'] ?? null) !== 'image/png') {
                throw new CertificateGenerationException(
                    'The configured RES signatory signature failed integrity verification.',
                    'configured_signature_invalid',
                );
            }

            return [$path, $name];
        }

        $path = base_path('resources/certificates/res-signatory-signature.png');
        $this->assertVerifiedResource($path, self::OFFICIAL_SIGNATURE_SHA256, 'official_signature_invalid');

        return [$path, $name];
    }
}
