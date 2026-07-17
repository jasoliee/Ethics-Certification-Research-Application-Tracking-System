<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
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
