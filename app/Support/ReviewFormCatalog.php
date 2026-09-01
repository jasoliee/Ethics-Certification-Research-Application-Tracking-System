<?php

namespace App\Support;

use App\Enums\ReviewFormType;

/**
 * Immutable, server-owned transcription and placement manifest for the official
 * KLD Research Ethics Unit review forms.
 */
final class ReviewFormCatalog
{
    public const CATALOG_VERSION = 'rems-review-forms-7231e839-v1';

    public const TEMPLATE_SHA256 = '7231e839ed75dc8d3977f84c35d62c552b5b1919b4065efa412dbd122c960f16';

    public const GENERATOR_VERSION = 'ecrats-fpdi-6-centered-signature-line';

    /** @return array{version: string, sha256: string, generator_version: string, source_pages: array<int, int>} */
    public static function template(ReviewFormType $type): array
    {
        return [
            'version' => self::CATALOG_VERSION,
            'sha256' => self::TEMPLATE_SHA256,
            'generator_version' => self::GENERATOR_VERSION,
            'source_pages' => match ($type) {
                ReviewFormType::Protocol => [1, 2, 3],
                ReviewFormType::InformedConsent => [7, 8],
            },
        ];
    }

    public static function templatePath(): string
    {
        return resource_path('assets/official/rems-review-forms.pdf');
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     printed_number: int|null,
     *     text: string,
     *     source_page: int,
     *     answer_y_mm: float,
     *     comment_source_page?: int,
     *     comment_y_mm?: float
     * }>
     */
    public static function items(ReviewFormType $type): array
    {
        return match ($type) {
            ReviewFormType::Protocol => self::protocolItems(),
            ReviewFormType::InformedConsent => self::consentItems(),
        };
    }

    /** @return array<string, string> */
    public static function questions(ReviewFormType $type): array
    {
        return array_map(
            static fn (array $item): string => $item['text'],
            self::items($type),
        );
    }

    /** @return array<string, string> */
    public static function answers(ReviewFormType $type): array
    {
        return match ($type) {
            // The option order mirrors the printed worksheet columns.
            ReviewFormType::Protocol => [
                'no' => 'No',
                'yes' => 'Yes',
                'unable_to_assess' => 'Unable to Assess',
            ],
            ReviewFormType::InformedConsent => [
                'yes' => 'Yes',
                'no' => 'No',
            ],
        };
    }

    /** @return array<string, array<string, int|float|string|null>> */
    private static function protocolItems(): array
    {
        return self::keyBy([
            self::item('protocol_01', 1, 'Does study have social value? (e.g. scientific value, relevance to national /community needs)', 1, 122.0, 1, 132.0),
            self::item('protocol_02', 2, 'Is the study background adequate?', 1, 138.3, 1, 148.0),
            self::item('protocol_03', 3, 'Are the research questions supported by the Review of Literature?', 1, 155.2, 1, 169.5),
            self::item('protocol_04', 4, '(For pure Qualitative) Are the study objectives having Credibility, Transferability, Dependability, Feasibility, Flexibility, Confirmability? (For pure Quantitative, systematic literature reviews (SLRs), and others) Are the study objectives Specific, Measurable, Attainable, Realistic, Time-bound?', 1, 176.7, 1, 223.0),
            self::item('protocol_05', 5, 'Is the research design appropriate? Is the population identified and defined? Is the selection of study participants described? Is the sample size justified? Is the plan for data analysis described? Are there dummy tables?', 1, 230.0, 2, 76.8),
            self::item('protocol_06', 6, 'Does the research need to be carried out with human participants?', 2, 83.7, 2, 98.2),
            self::item('protocol_07', 7, 'Does the study have a vulnerability issue?', 2, 104.9, 2, 114.5),
            self::item('protocol_08', 8, 'Are appropriate mechanisms/interventions in place to address the vulnerability issue/s?', 2, 121.1, 2, 135.2),
            self::item('protocol_09', 9, 'Are there risks/probable harms to the human participants in the study?', 2, 141.9, 2, 156.7),
            self::item('protocol_10', 10, 'Are there measures to mitigate the risks?', 2, 163.4, 2, 173.0),
            self::item('protocol_11', 11, 'Is the informed consent procedure/ form and culturally appropriate?', 2, 179.6, 2, 194.4),
            self::item('protocol_12', 12, 'Is/are the investigator/s adequately trained and do they have sufficient experience to undertake the study?', 2, 201.0, 2, 215.8),
            self::item('protocol_13', 13, 'Is there a disclosure of conflict of interest?', 2, 222.5, 2, 232.0),
            self::item('protocol_14', 14, 'Are the research facilities adequate?', 2, 238.7, 2, 248.2),
            // Stable response key stays unchanged while the corrected UI and generated record use item 15.
            self::item('protocol_15', 15, 'Are there any other concerns in the study?', 3, 74.0, 3, 83.5),
        ]);
    }

    /** @return array<string, array<string, int|float|string|null>> */
    private static function consentItems(): array
    {
        return self::keyBy([
            self::item('consent_01', null, 'Purpose of the study?', 7, 157.6),
            self::item('consent_02', null, 'Expected duration of participation?', 7, 163.1),
            self::item('consent_03', null, 'Procedures to be carried out?', 7, 169.8),
            self::item('consent_04', null, 'Discomforts and inconveniences?', 7, 175.3),
            self::item('consent_05', null, 'Risks (including possible discrimination)?', 7, 180.8),
            self::item('consent_06', null, 'Random assignment to the trial treatments?', 7, 186.3),
            self::item('consent_07', null, 'Benefits to the participants?', 7, 191.8),
            self::item('consent_08', null, 'Alternative treatments/ procedures?', 7, 198.0),
            self::item('consent_09', null, 'Compensation and/or medical treatments in case of injury?', 7, 203.5),
            self::item('consent_10', null, 'Who to contact for pertinent questions and/ or for assistance in a research- related injury?', 7, 210.2),
            self::item('consent_11', null, 'Refusal to participate or discontinuance at any time will involve penalty or loss of benefits to which the subject is entitled?', 7, 221.0),
            self::item('consent_12', null, 'Extent of confidentiality?', 7, 236.7),
            self::item('consent_13', null, 'Is the informed consent written or presented in simple language that participants can understand?', 8, 71.7),
            self::item('consent_14', null, 'Does the protocol include an adequate process for ensuring that consent is voluntary?', 8, 82.4),
            self::item('consent_15', null, 'Do you have any other concerns?', 8, 95.4),
        ]);
    }

    /** @return array<string, int|float|string|null> */
    private static function item(
        string $key,
        ?int $printedNumber,
        string $text,
        int $sourcePage,
        float $answerY,
        ?int $commentSourcePage = null,
        ?float $commentY = null,
    ): array {
        return array_filter([
            'key' => $key,
            'printed_number' => $printedNumber,
            'text' => $text,
            'source_page' => $sourcePage,
            'answer_y_mm' => $answerY,
            'comment_source_page' => $commentSourcePage,
            'comment_y_mm' => $commentY,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<int, array<string, int|float|string|null>>  $items
     * @return array<string, array<string, int|float|string|null>>
     */
    private static function keyBy(array $items): array
    {
        $keyed = [];

        foreach ($items as $item) {
            $keyed[(string) $item['key']] = $item;
        }

        return $keyed;
    }
}
