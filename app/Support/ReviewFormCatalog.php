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
            'protocol_01' => 'Does study have social value? (e.g. scientific value, relevance to national/community needs)',
            'protocol_02' => 'Is the study background adequate?',
            'protocol_03' => 'Are the research questions supported by the Review of Literature?',
            'protocol_04' => 'For pure qualitative studies: Are the study objectives having Credibility, Transferability, Dependability, Feasibility, Flexibility, Confirmability? For pure quantitative studies, systematic literature reviews (SLRs), and others: Are the study objectives Specific, Measurable, Attainable, Realistic, Time-bound?',
            'protocol_05' => 'Is the research design appropriate? Is the population identified and defined? Is the selection of study participants described? Is the sample size justified? Is the plan for data analysis described? Are there dummy tables?',
            'protocol_06' => 'Does the research need to be carried out with human participants?',
            'protocol_07' => 'Does the study have a vulnerability issue?',
            'protocol_08' => 'Are appropriate mechanisms/interventions in place to address the vulnerability issue/s?',
            'protocol_09' => 'Are there risks/probable harms to the human participants in the study?',
            'protocol_10' => 'Are there measures to mitigate the risks?',
            'protocol_11' => 'Is the informed consent procedure/form culturally appropriate?',
            'protocol_12' => 'Is/are the investigator/s adequately trained and do they have sufficient experience to undertake the study?',
            'protocol_13' => 'Is there a disclosure of conflict of interest?',
            'protocol_14' => 'Are the research facilities adequate?',
            'protocol_15' => 'Are there any other concerns in the study?',
        ];
    }

    /** @return array<string, string> */
    private static function consentQuestions(): array
    {
        return [
            'consent_01' => 'Purpose of the study?',
            'consent_02' => 'Expected duration of participation?',
            'consent_03' => 'Procedures to be carried out?',
            'consent_04' => 'Discomforts and inconveniences?',
            'consent_05' => 'Risks (including possible discrimination)?',
            'consent_06' => 'Random assignment to the trial treatments?',
            'consent_07' => 'Benefits to the participants?',
            'consent_08' => 'Alternative treatments/procedures?',
            'consent_09' => 'Compensation and/or medical treatments in case of injury?',
            'consent_10' => 'Who to contact for pertinent questions and/or for assistance in a research-related injury?',
            'consent_11' => 'Refusal to participate or discontinuance at any time will involve penalty or loss of benefits to which the subject is entitled?',
            'consent_12' => 'Extent of confidentiality?',
            'consent_13' => 'Is the informed consent written or presented in simple language that participants can understand?',
            'consent_14' => 'Does the protocol include an adequate process for ensuring that consent is voluntary?',
            'consent_15' => 'Do you have any other concerns?',
        ];
    }
}
