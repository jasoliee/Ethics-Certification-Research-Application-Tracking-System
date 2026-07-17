<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_login_fields_show_validation_errors(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors(['username', 'password']);
    }

    public function test_invalid_credentials_show_error_message(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->from('/login')->post('/login', [
            'username' => 'reslead',
            'password' => 'wrongpass',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'credentials' => 'The username or password is incorrect.',
            ]);

        $this->followingRedirects()->from('/login')->post('/login', [
            'username' => 'reslead',
            'password' => 'wrongpass',
        ])
            ->assertOk()
            ->assertSee('The username or password is incorrect.')
            ->assertSee('data-error-for="credentials"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertDontSee('class="login-error"', false);

        $this->assertGuest();
    }

    public function test_testing_accounts_login_to_their_role_landing_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $accounts = [
            ['applicanttest', '12345678', 'applicant.landing', 'Student/Faculty Researcher Landing Page'],
            ['advisertest', '12345678', 'adviser.landing', 'Adviser Landing Page'],
            ['reviewertest', '12345678', 'reviewer.landing', 'Reviewer Landing Page'],
            ['reslead', '12345kld', 'res.landing', 'RES Lead Landing Page'],
        ];

        foreach ($accounts as [$username, $password, $routeName, $landingTitle]) {
            $this->post('/logout');

            $this->post('/login', [
                'username' => $username,
                'password' => $password,
            ])->assertRedirect(route($routeName));

            $this->assertAuthenticated();
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee($landingTitle)
                ->assertSee('Logout');
        }
    }

    public function test_every_user_is_redirected_away_from_other_role_landing_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $accounts = [
            'applicanttest' => 'applicant.landing',
            'advisertest' => 'adviser.landing',
            'reviewertest' => 'reviewer.landing',
            'reslead' => 'res.landing',
        ];

        foreach ($accounts as $username => $authorizedRoute) {
            $user = User::where('username', $username)->firstOrFail();

            foreach ($accounts as $targetRoute) {
                if ($targetRoute === $authorizedRoute) {
                    continue;
                }

                $this->actingAs($user)
                    ->get(route($targetRoute))
                    ->assertRedirect(route($authorizedRoute));
            }

            Auth::logout();
        }
    }

    public function test_logout_clears_authenticated_session(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('username', 'reslead')->firstOrFail();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->get(route('res.landing'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_cannot_view_or_submit_the_login_form(): void
    {
        $this->seed(DatabaseSeeder::class);

        $accounts = [
            'applicanttest' => 'applicant.landing',
            'advisertest' => 'adviser.landing',
            'reviewertest' => 'reviewer.landing',
            'reslead' => 'res.landing',
        ];

        foreach ($accounts as $username => $routeName) {
            $user = User::where('username', $username)->firstOrFail();

            $this->actingAs($user)
                ->get('/login')
                ->assertRedirect(route($routeName));

            $this->post('/login', [
                'username' => 'not-the-current-user',
                'password' => 'incorrect',
            ])->assertRedirect(route($routeName));

            $this->assertAuthenticatedAs($user);
            $this->post('/logout')->assertRedirect(route('login'));
        }
    }

    public function test_login_and_protected_responses_disable_browser_caching(): void
    {
        $loginResponse = $this->get('/login');

        $this->assertStringContainsString('no-store', (string) $loginResponse->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $loginResponse->headers->get('Pragma'));
        $this->assertSame('0', $loginResponse->headers->get('Expires'));

        $user = User::factory()->create(['role' => UserRole::Reviewer]);
        $protectedResponse = $this->actingAs($user)->get(route('reviewer.landing'));

        $this->assertStringContainsString('no-store', (string) $protectedResponse->headers->get('Cache-Control'));
    }

    public function test_database_seeding_is_idempotent_and_passwords_are_hashed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $accounts = [
            'applicanttest' => ['12345678', UserRole::Applicant],
            'advisertest' => ['12345678', UserRole::Adviser],
            'reviewertest' => ['12345678', UserRole::Reviewer],
            'reslead' => ['12345kld', UserRole::ResLead],
        ];

        foreach ($accounts as $username => [$plainPassword, $role]) {
            $user = User::where('username', $username)->firstOrFail();

            $this->assertSame(1, User::where('username', $username)->count());
            $this->assertSame($role, $user->role);
            $this->assertNotSame($plainPassword, $user->password);
            $this->assertTrue(Hash::check($plainPassword, $user->password));
        }
    }

    public function test_username_and_password_boundaries_are_enforced_on_login_request(): void
    {
        $this->from('/login')->post('/login', [
            'username' => str_repeat('a', 30),
            'password' => '12345678',
        ])->assertSessionDoesntHaveErrors('username')
            ->assertSessionHasErrors('credentials');

        $this->post('/login', [
            'username' => str_repeat('b', 31),
            'password' => '12345678',
        ])->assertSessionHasErrors('username');

        $this->post('/login', [
            'username' => 'reslead',
            'password' => '1234567',
        ])->assertSessionHasErrors('password');

        $this->post('/login', [
            'username' => 'reslead',
            'password' => '12345678901234567',
        ])->assertSessionHasErrors('password');
    }

    public function test_login_username_is_trimmed_before_authentication(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', [
            'username' => '  applicanttest  ',
            'password' => '12345678',
        ])->assertRedirect(route('applicant.landing'));

        $this->assertAuthenticated();
    }
}
