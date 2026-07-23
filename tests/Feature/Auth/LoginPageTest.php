<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSee('Login Account')
            ->assertSee('Contact your administrator')
            ->assertSee('https://kld.edu.ph/office_of_the_vice_president_for_research_mission_and_external_affairs.php')
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('maxlength="30"', false)
            ->assertSee('id="login-validation-messages"', false)
            ->assertSee('aria-invalid="false"', false)
            ->assertSee('type="password"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('type="button"', false)
            ->assertSee('aria-label="Show password"', false)
            ->assertSee('aria-controls="password"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('data-password-state-indicator', false)
            ->assertDontSee('Register')
            ->assertDontSee('Create account')
            ->assertDontSee('class="login-error"', false);

        $this->assertMatchesRegularExpression(
            '/<button(?=[^>]*data-password-toggle)(?=[^>]*\shidden(?:\s|>))[^>]*>/s',
            $response->getContent(),
        );
    }

    public function test_root_page_shows_login(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Ethics Review Section')
            ->assertSee('Login Account');
    }

    public function test_public_registration_route_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_reset_password_page_has_custom_password_visibility_controls(): void
    {
        $response = $this->get(route('password.reset', [
            'token' => str_repeat('a', 64),
            'email' => 'applicant.setup@example.test',
        ]));

        $response
            ->assertOk()
            ->assertSee('aria-controls="reset-password"', false)
            ->assertSee('aria-controls="reset-password-confirmation"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('type="button"', false)
            ->assertSee('aria-label="Show password"', false)
            ->assertSee('data-password-state-indicator', false);

        $this->assertSame(2, substr_count($response->getContent(), 'data-password-toggle'));
    }

    public function test_password_setup_redirects_to_login_with_generated_username(): void
    {
        $user = User::factory()->pendingSetup()->create([
            'email' => 'applicant.setup@example.test',
            'username' => 'kld.stu.2024.santos',
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->followingRedirects()->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertOk()
            ->assertSee('Your password has been reset. You can now log in with your username.')
            ->assertSee('kld.stu.2024.santos')
            ->assertSee('value="kld.stu.2024.santos"', false);

        $user->refresh();

        $this->assertSame('active', $user->account_status);
        $this->assertNotNull($user->password_setup_completed_at);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_login_styles_use_one_continuous_page_background(): void
    {
        $styles = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($styles);
        $this->assertSame(1, substr_count($styles, 'loginbackground.jpg'));
        $this->assertStringContainsString('background: rgba(0, 0, 0, 0.55);', $styles);
        $this->assertStringContainsString('background: rgba(255, 255, 255, 0.88);', $styles);
        $this->assertStringNotContainsString('linear-gradient', $styles);
        $this->assertStringContainsString('input::-ms-reveal', $styles);
        $this->assertMatchesRegularExpression('/\.login-shell\s*\{[^}]*border:\s*0;/s', $styles);
    }
}
