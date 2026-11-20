<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_login_form_and_google_sign_in_link(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('login.store'))
            ->assertSee(route('google.redirect'));

        $this->get(route('register'))
            ->assertOk()
            ->assertSee(route('google.redirect'));
    }

    public function test_login_shows_the_password_form_after_google_sign_in_fails(): void
    {
        $this->withSession(['status' => 'Google sign-in was cancelled or not authorized.'])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('Google sign-in was cancelled or not authorized.')
            ->assertSee(route('login.store'));
    }

    public function test_login_shows_the_password_form_when_password_validation_fails(): void
    {
        $user = User::factory()->create(['name' => 'Login Student']);

        $this->from(route('login'))->post(route('login.store'), [
            'name' => $user->name,
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('The name or password is incorrect.')
            ->assertSee(route('login.store'));
    }

    public function test_google_redirect_stores_state_and_requests_the_expected_scopes(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $response = $this->get(route('google.redirect'));
        $response->assertRedirect();

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('openid email profile', $query['scope'] ?? null);
        $this->assertSame(session('google_oauth_state'), $query['state'] ?? null);
    }

    public function test_callback_rejects_invalid_state_and_cancelled_sign_in(): void
    {
        $this->withSession(['google_oauth_state' => 'expected-state'])
            ->get(route('google.callback', ['code' => 'auth-code', 'state' => 'wrong-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Google sign-in could not be verified. Please try again.');

        $this->withSession(['google_oauth_state' => 'cancelled-state'])
            ->get(route('google.callback', ['error' => 'access_denied', 'state' => 'cancelled-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Google sign-in was cancelled or not authorized.');
    }

    public function test_callback_creates_a_student_account_and_starts_attendance(): void
    {
        $this->configureGoogleCredentials();
        $this->fakeGoogleProfile();

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect('/student/dashboard');

        $user = User::query()->where('email', 'student@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-subject-123', $user->google_id);
        $this->assertSame('student', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('attendances', ['user_id' => $user->id]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openidconnect.googleapis.com/v1/userinfo');
    }

    public function test_callback_links_a_verified_google_email_to_existing_account_without_changing_role(): void
    {
        $this->configureGoogleCredentials();
        $this->fakeGoogleProfile([
            'sub' => 'google-subject-admin',
            'email' => 'admin@example.com',
            'name' => 'Google Admin',
        ]);
        $admin = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($admin);
        $this->assertSame('google-subject-admin', $admin->fresh()->google_id);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_callback_does_not_replace_a_different_google_account_link(): void
    {
        $this->configureGoogleCredentials();
        $this->fakeGoogleProfile(['email' => 'linked@example.com']);
        $user = User::factory()->create([
            'email' => 'linked@example.com',
            'google_id' => 'another-google-subject',
        ]);

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This account is linked to a different Google account. Please use the linked account.');

        $this->assertGuest();
        $this->assertSame('another-google-subject', $user->fresh()->google_id);
    }

    public function test_callback_does_not_create_an_account_for_an_unverified_email(): void
    {
        $this->configureGoogleCredentials();
        $this->fakeGoogleProfile(['email_verified' => false]);

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Google did not provide a verified email profile. Please try another account.');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_callback_handles_google_network_failures(): void
    {
        $this->configureGoogleCredentials();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::failedConnection(),
        ]);

        $this->withSession(['google_oauth_state' => 'valid-state'])
            ->get(route('google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Google could not be reached. Please try again.');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    private function configureGoogleCredentials(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function fakeGoogleProfile(array $overrides = []): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(array_merge([
                'sub' => 'google-subject-123',
                'email' => 'student@example.com',
                'email_verified' => true,
                'name' => 'Google Student',
            ], $overrides)),
        ]);

    }
}
