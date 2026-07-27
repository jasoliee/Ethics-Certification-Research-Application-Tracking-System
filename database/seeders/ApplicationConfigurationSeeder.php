<?php

namespace Database\Seeders;

use App\Models\DocumentRequirement;
use Illuminate\Database\Seeder;

/**
 * Seeds the approved baseline requirement catalog without inventing time-bound submission dates.
 */
class ApplicationConfigurationSeeder extends Seeder
{
    /**
     * Create or refresh the four initial requirements used by Thesis and Capstone applications.
     */
    public function run(): void
    {
        $requirements = [
            [
                'code' => 'RESEARCH-PROPOSAL',
                'name' => 'Research Proposal',
                'description' => 'Complete research proposal for ethics review.',
            ],
            [
                'code' => 'KLD-RES-04-001B',
                'name' => 'Research Ethics Compliance Agreement',
                'description' => 'Signed institutional research ethics compliance agreement.',
            ],
            [
                'code' => 'KLD-RES-04-003',
                'name' => 'Informed Consent',
                'description' => 'Participant-facing informed consent document.',
            ],
            [
                'code' => 'PAYMENT-PROOF',
                'name' => 'Payment Proof',
                'description' => 'Uploaded proof retained for Research Adviser verification.',
            ],
        ];

        // Upsert by stable requirement code so repeated seeding preserves linked application documents.
        foreach ($requirements as $index => $requirement) {
            DocumentRequirement::updateOrCreate(
                ['code' => $requirement['code']],
                [
                    ...$requirement,
                    'is_mandatory' => true,
                    'research_types' => null,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
