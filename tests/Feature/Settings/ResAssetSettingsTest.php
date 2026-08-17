<?php

namespace Tests\Feature\Settings;

use App\Enums\ProfileOptionField;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CertificateBackground;
use App\Models\ProfileOption;
use App\Models\User;
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

    public function test_res_signatory_requires_a_transparent_png_and_is_stored_privately(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $signature = new UploadedFile(
            resource_path('certificates/res-signatory-signature.png'),
            'authorized-signature.png',
            'image/png',
            null,
            true,
        );

        $this->actingAs($resLead)
            ->put(route('res.settings.signatory.update'), [
                'certificate_signatory_name' => 'Dr. Authorized Signatory',
                'signature' => $signature,
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $resLead->refresh();
        $this->assertSame('Dr. Authorized Signatory', $resLead->certificate_signatory_name);
        $this->assertNotNull($resLead->certificate_signature_sha256);
        Storage::disk('local')->assertExists($resLead->certificate_signature_path);
        $this->actingAs($resLead)
            ->get(route('res.settings.signatory.preview'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
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
