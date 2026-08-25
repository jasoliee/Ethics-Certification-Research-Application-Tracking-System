<?php

namespace Tests\Feature\Settings;

use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CertificateBackground;
use App\Models\ProfileOption;
use App\Models\User;
use App\Services\Identity\ProfileOptionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResAssetSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_settings_contains_moved_dropdown_options_and_independent_background_histories(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->get(route('res.settings.index', ['tab' => 'backgrounds']))
            ->assertOk()
            ->assertSee('Dropdown Options')
            ->assertSee('Background Management')
            ->assertSee('Certificate Background')
            ->assertSee('Review Worksheet Background')
            ->assertSee(route('res.settings.backgrounds.store'), false)
            ->assertSee('Choose File');

        $certificate = CertificateBackground::query()
            ->where('background_type', CertificateBackground::TYPE_CERTIFICATE)
            ->where('is_active', true)
            ->firstOrFail();
        $worksheet = CertificateBackground::query()
            ->where('background_type', CertificateBackground::TYPE_REVIEW_WORKSHEET)
            ->where('is_active', true)
            ->firstOrFail();
        $this->assertNotSame($certificate->id, $worksheet->id);

        $upload = new UploadedFile(
            resource_path('certificates/res-certificate-background.jpeg'),
            'worksheet-background.jpeg',
            'image/jpeg',
            null,
            true,
        );
        $this->actingAs($resLead)
            ->post(route('res.settings.backgrounds.store'), [
                'background_type' => CertificateBackground::TYPE_REVIEW_WORKSHEET,
                'background' => $upload,
            ])
            ->assertRedirect(route('res.settings.index', ['tab' => 'backgrounds']))
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue($certificate->refresh()->is_active);
        $this->assertFalse($worksheet->refresh()->is_active);
        $this->assertSame(2, CertificateBackground::query()
            ->where('background_type', CertificateBackground::TYPE_REVIEW_WORKSHEET)
            ->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'review_worksheet.background_activated',
            'subject_id' => CertificateBackground::query()
                ->where('background_type', CertificateBackground::TYPE_REVIEW_WORKSHEET)
                ->where('is_active', true)
                ->value('id'),
        ]);

        $activeWorksheet = CertificateBackground::query()
            ->where('background_type', CertificateBackground::TYPE_REVIEW_WORKSHEET)
            ->where('is_active', true)
            ->firstOrFail();
        Storage::disk('local')->put($activeWorksheet->stored_file_path, 'tampered');
        $this->actingAs($resLead)
            ->get(route('res.settings.backgrounds.preview', $activeWorksheet))
            ->assertNotFound();
    }

    public function test_res_can_manage_dropdown_options_only_inside_authorized_settings_routes(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create();

        $this->actingAs($resLead)
            ->post(route('res.settings.profile-options.store'), [
                'option_field' => ProfileOptionField::Program->value,
                'option_value' => 'Bachelor of Science in Data Ethics',
            ])
            ->assertRedirect(route('res.settings.index', ['tab' => 'options']));
        $this->assertDatabaseHas('profile_options', [
            'field' => ProfileOptionField::Program->value,
            'normalized_value' => 'bachelor of science in data ethics',
        ]);

        $option = ProfileOption::query()->where('normalized_value', 'bachelor of science in data ethics')->firstOrFail();
        $this->actingAs($applicant)
            ->put(route('res.settings.profile-options.update', $option), ['option_value' => 'Forged'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame('Bachelor of Science in Data Ethics', $option->refresh()->value);

        $this->actingAs($resLead)
            ->get(route('res.users.profile-options.index'))
            ->assertRedirect('/res-lead/settings?tab=options');
    }

    public function test_default_profile_options_include_editable_institute_acronyms(): void
    {
        $expectedInstitutes = [
            'Institute of Behavioral Sciences' => 'IBS',
            'Institute of Computing and Digital Innovation' => 'ICDI',
            'Institute of Engineering' => 'IOE',
            'Institute of Foundational Studies' => 'IFS',
            'Institute of Governance and Development Studies' => 'IGDS',
            'Institute of Medical Laboratory Science' => 'IMLS',
            'Institute of Midwifery' => 'IOM',
            'Institute of Nursing' => 'ION',
            'Institute of Science and Mathematics' => 'ISM',
        ];
        $expectedPrograms = [
            'Bachelor of Science in Psychology',
            'Bachelor of Science in Computer Science',
            'Bachelor of Science in Data Science',
            'Bachelor of Science in Information Systems',
            'Bachelor of Science in Civil Engineering',
            'Bachelor of Science in Social Work',
            'Bachelor of Science in Medical Laboratory Science',
            'Bachelor of Science in Midwifery',
            'Bachelor of Science in Nursing',
            'Bachelor of Science in Life Sciences',
        ];

        $this->assertSame(
            $expectedInstitutes,
            ProfileOption::query()
                ->where('field', ProfileOptionField::Institute->value)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('acronym', 'value')
                ->all(),
        );
        $this->assertSame(
            ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            ProfileOption::query()
                ->where('field', ProfileOptionField::YearLevel->value)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('value')
                ->all(),
        );
        $this->assertSame(
            $expectedPrograms,
            ProfileOption::query()
                ->where('field', ProfileOptionField::Program->value)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('value')
                ->all(),
        );

        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $this->actingAs($resLead)
            ->get(route('res.settings.index', ['tab' => 'options']))
            ->assertOk()
            ->assertSee('Institute Acronym')
            ->assertSee('ICDI')
            ->assertSee('data-profile-option-acronym', false);

        $this->actingAs($resLead)
            ->post(route('res.settings.profile-options.store'), [
                'option_field' => ProfileOptionField::Institute->value,
                'option_value' => 'Institute of Applied Research',
            ])
            ->assertSessionHasErrors('option_acronym');

        $this->actingAs($resLead)
            ->post(route('res.settings.profile-options.store'), [
                'option_field' => ProfileOptionField::Institute->value,
                'option_value' => 'Institute of Applied Research',
                'option_acronym' => 'iar',
            ])
            ->assertRedirect(route('res.settings.index', ['tab' => 'options']))
            ->assertSessionDoesntHaveErrors();
        $option = ProfileOption::query()->where('value', 'Institute of Applied Research')->firstOrFail();
        $this->assertSame('IAR', $option->acronym);

        $this->actingAs($resLead)
            ->put(route('res.settings.profile-options.update', $option), [
                'option_value' => 'Institute of Applied and Emerging Research',
                'option_acronym' => 'iaer',
            ])
            ->assertRedirect(route('res.settings.index', ['tab' => 'options']))
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('profile_options', [
            'id' => $option->id,
            'value' => 'Institute of Applied and Emerging Research',
            'acronym' => 'IAER',
        ]);
        $catalog = app(ProfileOptionCatalog::class);
        $this->assertSame('IAER', $catalog->instituteAcronym('Institute of Applied Research'));
        $this->assertSame(
            'Institute of Applied Research (IAER)',
            $catalog->instituteLabelWithAcronym('Institute of Applied Research'),
        );

        $this->actingAs($resLead)
            ->post(route('res.settings.profile-options.store'), [
                'option_field' => ProfileOptionField::Institute->value,
                'option_value' => 'Institute of Acronym Collision',
                'option_acronym' => 'ICDI',
            ])
            ->assertSessionHasErrors('option_acronym');
    }

    public function test_res_signatory_requires_a_transparent_png_and_is_stored_privately(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        $this->actingAs($resLead)
            ->get(route('res.settings.index', ['tab' => 'certificate']))
            ->assertOk()
            ->assertSee('Current Signature')
            ->assertSee('Current QR Code')
            ->assertSee('Replace Signature')
            ->assertSee('Replace QR')
            ->assertSee('The signature must be a transparent PNG file without a background.')
            ->assertDontSee('Transparent PNG Signature')
            ->assertDontSee('>QR Image<', false)
            ->assertSee('data-settings-date-picker', false);
        $signature = new UploadedFile(
            resource_path('certificates/res-signatory-signature.png'),
            'authorized-signature.png',
            'image/png',
            null,
            true,
        );
        $qrImage = UploadedFile::fake()->image('certificate-qr.png', 256, 256);
        $qrBytes = file_get_contents($qrImage->getRealPath());
        $this->assertIsString($qrBytes);

        $this->actingAs($resLead)
            ->put(route('res.settings.signatory.update'), [
                'certificate_signatory_name' => 'Dr. Authorized Signatory',
                'certificate_valid_until' => now()->addYear()->format('Y-m-d'),
                'signature' => $signature,
                'qr_image' => $qrImage,
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $resLead->refresh();
        $this->assertSame('Dr. Authorized Signatory', $resLead->certificate_signatory_name);
        $this->assertNotNull($resLead->certificate_signature_sha256);
        $this->assertSame(hash('sha256', $qrBytes), $resLead->certificate_qr_sha256);
        $this->assertSame(256, $resLead->certificate_qr_width);
        $this->assertSame(256, $resLead->certificate_qr_height);
        Storage::disk('local')->assertExists($resLead->certificate_signature_path);
        Storage::disk('local')->assertExists($resLead->certificate_qr_path);
        $this->actingAs($resLead)
            ->get(route('res.settings.signatory.preview'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->actingAs($resLead)
            ->get(route('res.settings.certificate-qr.preview'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->actingAs(User::factory()->create())
            ->get(route('res.settings.certificate-qr.preview'))
            ->assertRedirect(route('dashboard'));
        $this->assertSame(1, AuditLog::query()->where('action', 'settings.certificate_signatory_updated')->count());

        $opaqueImage = imagecreatetruecolor(200, 100);
        imagefill($opaqueImage, 0, 0, imagecolorallocate($opaqueImage, 255, 255, 255));
        ob_start();
        imagepng($opaqueImage);
        $opaqueBytes = (string) ob_get_clean();
        imagedestroy($opaqueImage);
        $opaqueFile = UploadedFile::fake()->createWithContent('not-transparent.png', $opaqueBytes);
        $this->actingAs($resLead)
            ->put(route('res.settings.signatory.update'), [
                'certificate_signatory_name' => 'Should Not Replace',
                'signature' => $opaqueFile,
            ])
            ->assertSessionHasErrorsIn('signatory', 'signature');
        $this->assertSame('Dr. Authorized Signatory', $resLead->refresh()->certificate_signatory_name);
    }
}
