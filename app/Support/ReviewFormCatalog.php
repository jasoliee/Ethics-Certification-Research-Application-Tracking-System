<?php

namespace App\Support;

use App\Enums\ReviewFormType;

/**
 * Keeps the two official Reviewer form contracts immutable and server-owned.
 */
final class ReviewFormCatalog
{
    /** @return array<string, string> */
    public static function questions(ReviewFormType $type): array
    {
        return match ($type) {
            ReviewFormType::Protocol => self::protocolQuestions(),
            ReviewFormType::InformedConsent => self::consentQuestions(),
        };
    }

    /** @return array<string, string> */
    public static function answers(ReviewFormType $type): array
    {
        return match ($type) {
            ReviewFormType::Protocol => [
                'yes' => 'Yes',
                'no' => 'No',
                'unable_to_assess' => 'Unable to Assess',
            ],
            ReviewFormType::InformedConsent => [
                'yes' => 'Yes',
                'no' => 'No',
            ],
        };
    }

    /** @return array<string, string> */
    private static function protocolQuestions(): array
    {
        return [
            'protocol_01' => 'Does the study have social value?',
            'protocol_02' => 'Is the study background adequate?',
            'protocol_03' => 'Are the research questions supported by the Review of Literature?',
            'protocol_04' => 'Are the study objectives appropriate for the selected qualitative or quantitative design?',
            'protocol_05' => 'Is the research design appropriate, including population, participant selection, sample size, and data analysis?',
            'protocol_06' => 'Does the research need to be carried out with human participants?',
            'protocol_07' => 'Does the study have a vulnerability issue?',
            'protocol_08' => 'Are appropriate mechanisms or interventions in place to address vulnerability issues?',
            'protocol_09' => 'Are there risks or probable harms to human participants in the study?',
            'protocol_10' => 'Are there measures to mitigate the risks?',
            'protocol_11' => 'Is the informed consent procedure or form culturally appropriate?',
            'protocol_12' => 'Are the investigators adequately trained and sufficiently experienced to undertake the study?',
            'protocol_13' => 'Is there a disclosure of conflict of interest?',
            'protocol_14' => 'Are the research facilities adequate?',
            'protocol_15' => 'Are there any other concerns in the study?',
        ];
    }

    /** @return array<string, string> */
    private static function consentQuestions(): array
    {
        return [
            'consent_01' => 'Purpose of the study',
            'consent_02' => 'Expected duration of participation',
            'consent_03' => 'Procedures to be carried out',
            'consent_04' => 'Discomforts and inconveniences',
            'consent_05' => 'Risks, including possible discrimination',
            'consent_06' => 'Benefits to the participants',
            'consent_07' => 'Who to contact for questions or assistance in a research-related injury',
            'consent_08' => 'Whether refusal or discontinuance involves no penalty or loss of benefits',
            'consent_09' => 'Extent of confidentiality',
            'consent_10' => 'The consent is written or presented in simple language participants can understand',
            'consent_11' => 'The protocol includes an adequate process to ensure consent is voluntary',
            'consent_12' => 'Any other concerns',
        ];
    }
}
