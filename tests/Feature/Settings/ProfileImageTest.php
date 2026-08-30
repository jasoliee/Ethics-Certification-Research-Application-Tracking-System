<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Settings\ProfileImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_roles_can_replace_view_and_restore_their_initials(): void
    {
        Storage::fake('local');

        foreach ([UserRole::Applicant, UserRole::Adviser, UserRole::ResLead] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $upload = $this->actingAs($user)->post(route('profile-image.store'), [
                'profile_image' => UploadedFile::fake()->image('avatar.jpg', 320, 320),
            ]);
            $upload->assertRedirect()->assertSessionHas('status', 'Profile image updated.');

            $path = app(ProfileImageService::class)->path($user);
            $this->assertNotNull($path);
            Storage::disk('local')->assertExists($path);

            $imageResponse = $this->actingAs($user)->get(route('profile-image.show'));
            $imageResponse->assertOk();
            $this->assertStringContainsString('no-store', (string) $imageResponse->headers->get('cache-control'));

            $this->actingAs($user)->get(route('dashboard'))
                ->assertOk()
                ->assertSee(route('profile-image.show'), false);

            $this->actingAs($user)->delete(route('profile-image.destroy'))
                ->assertRedirect()
                ->assertSessionHas('status', 'Profile image removed. Your initials are shown by default.');
            $this->assertNull(app(ProfileImageService::class)->path($user));
        }
    }

    public function test_profile_image_upload_rejects_non_image_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => UserRole::Applicant]);

        $this->actingAs($user)->post(route('profile-image.store'), [
            'profile_image' => UploadedFile::fake()->create('avatar.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('profile_image');

        $this->assertNull(app(ProfileImageService::class)->path($user));
    }
}
