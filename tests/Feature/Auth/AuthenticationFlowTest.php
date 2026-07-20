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

    public function test_testing_accounts_login_to_their_role_dashboard_at_the_canonical_url(): void
    {
        $this->seed(DatabaseSeeder::class);

        $accounts = [
            ['applicanttest', '12345678', 'No application yet'],
            ['advisertest', '12345678', 'Welcome back, Adviser!'],
            ['reviewertest', '12345678', 'Welcome back, Reviewer!'],
            ['reslead', '12345kld', 'Welcome back, RES Lead/Admin!'],
        ];

        foreach ($accounts as [$username, $password, $landingTitle]) {
            $this->post('/logout');

            $this->post('/login', [
                'username' => $username,
                'password' => $password,
            ])->assertRedirect(route('dashboard'));

            $this->assertAuthenticated();
            $this->get(route('dashboard'))
                ->assertOk()
                ->assertSee($landingTitle)
                ->assertSee('<title>Dashboard | ECRATS</title>', false)
                ->assertSee('Logout');
        }
    }

    public function test_every_user_is_redirected_away_from_other_role_areas(): void
    {
        $this->seed(DatabaseSeeder::class);

        $accounts = [
            'applicanttest' => 'applicant.settings.index',
            'advisertest' => 'adviser.settings.index',
            'reviewertest' => 'reviewer.settings.index',
            'reslead' => 'res.settings.index',
        ];

        foreach ($accounts as $username => $authorizedRoute) {
            $user = User::where('username', $username)->firstOrFail();

            foreach ($accounts as $targetRoute) {
                if ($targetRoute === $authorizedRoute) {
                    continue;
                }

                $this->actingAs($user)
                    ->get(route($targetRoute))
                    ->assertRedirect(route('dashboard'));
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
            'applicanttest',
            'advisertest',
            'reviewertest',
            'reslead',
        ];

        foreach ($accounts as $username) {
            $user = User::where('username', $username)->firstOrFail();

            $this->actingAs($user)
                ->get('/login')
                ->assertRedirect(route('dashboard'));

            $this->post('/login', [
                'username' => 'not-the-current-user',
                'password' => 'incorrect',
            ])->assertRedirect(route('dashboard'));

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
        $protectedResponse = $this->actingAs($user)->get(route('dashboard'));

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

    public function test_login_keeps_field_validation_separate_from_credential_mismatch(): void
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
        ])->assertSessionDoesntHaveErrors('password')
            ->assertSessionHasErrors('credentials');

        $this->post('/login', [
            'username' => 'reslead',
            'password' => str_repeat('a', 65),
        ])->assertSessionHasErrors('password');
    }

    public function test_login_username_is_trimmed_before_authentication(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', [
            'username' => '  applicanttest  ',
            'password' => '12345678',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }
}
