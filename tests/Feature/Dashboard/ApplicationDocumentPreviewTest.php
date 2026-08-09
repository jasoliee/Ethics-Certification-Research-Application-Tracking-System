<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApplicationDocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('officeDocumentProvider')]
    public function test_authorized_roles_receive_a_private_first_party_office_fallback(
        string $fileName,
        string $mimeType,
        string $typeLabel,
    ): void {
        Storage::fake('local');

        [$application, $document, $actors] = $this->privateDocumentFixture($fileName, $mimeType);

        foreach ($actors as [$actor, $routePrefix]) {
            $downloadUrl = route($routePrefix.'.applications.documents.download', [$application, $document]);

            $response = $this->actingAs($actor)
                ->get(route($routePrefix.'.applications.documents.preview', [$application, $document]))
                ->assertOk()
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertSee('Secure inline preview unavailable')
                ->assertSee($typeLabel)
                ->assertSee($downloadUrl, false)
                ->assertDontSee($document->stored_file_path);

            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function officeDocumentProvider(): array
    {
        return [
            'legacy Word' => [
                'protocol.doc',
                'application/msword',
                'Microsoft Word document (.doc)',
            ],
            'modern Word' => [
                'protocol.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Microsoft Word document (.docx)',
            ],
            'legacy Excel' => [
                'study-data.xls',
                'application/vnd.ms-excel',
                'Microsoft Excel workbook (.xls)',
            ],
            'modern Excel' => [
                'study-data.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Microsoft Excel workbook (.xlsx)',
            ],
        ];
    }

    #[DataProvider('browserNativeDocumentProvider')]
    public function test_browser_native_formats_stream_inline_with_private_headers(
        string $fileName,
        string $mimeType,
        string $contents,
    ): void {
        Storage::fake('local');

        [$application, $document, $actors] = $this->privateDocumentFixture($fileName, $mimeType, $contents);
        [$applicant] = $actors[0];

        $response = $this->actingAs($applicant)
            ->get(route('applicant.applications.documents.preview', [$application, $document]))
            ->assertOk()
            ->assertHeader('Content-Type', $mimeType)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertDontSee('Secure inline preview unavailable');

        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function browserNativeDocumentProvider(): array
    {
        return [
            'PDF' => ['protocol.pdf', 'application/pdf', '%PDF-1.4 protected'],
            'JPEG' => ['figure.jpg', 'image/jpeg', "\xFF\xD8\xFF protected"],
            'PNG' => ['figure.png', 'image/png', "\x89PNG protected"],
        ];
    }

    public function test_nested_preview_route_does_not_reveal_a_document_from_another_application(): void
    {
        Storage::fake('local');

        [$application, $document, $actors] = $this->privateDocumentFixture(
            'protocol.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
        [$applicant] = $actors[0];
        $otherApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant->id,
            'draft_owner_user_id' => $applicant->id,
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.applications.documents.preview', [$otherApplication, $document]))
            ->assertNotFound();
    }

    /**
     * @return array{
     *     ResearchApplication,
     *     ApplicationDocument,
     *     array<int, array{User, string}>
     * }
     */
    private function privateDocumentFixture(
        string $fileName,
        string $mimeType,
        string $contents = 'protected Office file',
    ): array {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant->id,
            'draft_owner_user_id' => null,
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::UnderExpeditedReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'submitted_at' => now()->subDays(3),
        ]);
        ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
        ]);
        $requirement = DocumentRequirement::create([
            'code' => 'PREVIEW-'.str()->uuid(),
            'name' => 'Protected research document',
            'is_mandatory' => true,
            'is_active' => true,
        ]);
        $document = ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => $fileName,
            'stored_file_path' => 'applications/private/'.str()->uuid(),
            'mime_type' => $mimeType,
            'file_size_bytes' => strlen($contents),
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now(),
        ]);
        Storage::disk('local')->put($document->stored_file_path, $contents);

        return [
            $application,
            $document,
            [
                [$applicant, 'applicant'],
                [$adviser, 'adviser'],
                [$resLead, 'res'],
                [$reviewer, 'reviewer'],
            ],
        ];
    }
}
