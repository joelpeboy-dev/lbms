<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_displays_local_library_illustration(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="lightning-backdrop"', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('images/library-login-art.svg')
            ->assertSee('An open book glowing in a library')
            ->assertSee('shooting star');
    }

    public function test_forgot_password_link_opens_the_recovery_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee('Account email');
    }

    public function test_password_recovery_resets_password_and_keeps_the_users_role(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'name' => 'Library Staff',
            'email' => 'staff@example.com',
            'password' => 'old-password',
            'role' => 'staff',
        ]);
        $accessToken = $user->createToken('password-reset-test')->accessToken;
        $this->assertModelExists($accessToken);

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'If an account matches that email, a password reset link has been sent.');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertIsString($token);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('staff@example.com');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertSame('staff', $user->role);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertModelMissing($accessToken);

        $this->post('/login', [
            'name' => $user->name,
            'password' => 'new-password-123',
        ])->assertRedirect('/staff/dashboard');
    }

    public function test_password_recovery_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an account matches that email, a password reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_invalid_password_reset_token_does_not_change_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_login_redirects_automatically_using_the_stored_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Library Staff',
            'email' => 'staff@example.com',
            'password' => 'password',
            'role' => 'staff',
        ]);

        $response = $this->post('/login', [
            'name' => $user->name,
            'password' => 'password',
        ]);

        $response->assertRedirect('/staff/dashboard')->assertSessionHas('just_logged_in', true);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'time_out' => null,
        ]);
    }

    public function test_each_role_sees_a_welcome_animation_once_after_login(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        foreach ([
            ['admin', '/admin/dashboard', 'Welcome Admin!'],
            ['staff', '/staff/dashboard', 'Welcome Staff!'],
            ['student', '/student/dashboard', 'Welcome Student!'],
        ] as [$role, $dashboard, $message]) {
            $user = User::factory()->create([
                'name' => 'Welcome '.$role,
                'password' => 'password',
                'role' => $role,
            ]);

            $this->post('/login', ['name' => $user->name, 'password' => 'password'])
                ->assertRedirect($dashboard)
                ->assertSessionHas('welcome_animation_user_id', $user->id);

            $this->get($dashboard)
                ->assertOk()
                ->assertSee($message)
                ->assertSee('welcome-animation')
                ->assertSee('aria-live="polite"', false);

            $this->get($dashboard)
                ->assertOk()
                ->assertDontSee('welcome-animation');

            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_legacy_log_in_url_redirects_to_the_login_page(): void
    {
        $this->get('/log%20in')
            ->assertRedirect(route('login'));
    }

    public function test_registration_creates_a_student_and_logs_them_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Registered Student',
            'email' => 'registered@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/student/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'registered@example.com',
            'role' => 'student',
        ]);
    }

    public function test_user_cannot_open_another_roles_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_dashboard_displays_current_library_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Book::create([
            'slug' => 'first-book',
            'category' => 'book',
            'title' => 'First Book',
            'author' => 'Author One',
            'description' => 'A library book.',
            'content' => 'Book content.',
        ]);
        Book::create([
            'slug' => 'study-book',
            'category' => 'study',
            'title' => 'Study Book',
            'author' => 'Author Two',
            'description' => 'A study book.',
            'content' => 'Study content.',
        ]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk()
            ->assertSee('<strong>1</strong>', false)
            ->assertSee('<strong>2</strong>', false);
    }

    public function test_admin_dashboard_displays_mapped_weather_data(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 29.4,
                    'relative_humidity_2m' => 72,
                    'weather_code' => 1,
                    'wind_speed_10m' => 12.5,
                ],
                'current_units' => [
                    'temperature_2m' => '°C',
                    'wind_speed_10m' => 'km/h',
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Manila')
            ->assertSee('29.4')
            ->assertSee('Partly cloudy')
            ->assertSee('72%')
            ->assertSee('12.5');
    }

    public function test_admin_dashboard_shows_weather_fallback_when_api_fails(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([], 503),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Weather information is temporarily unavailable.')
            ->assertSee('Welcome Admin!');
    }

    public function test_admin_dashboard_uses_configured_default_location_when_custom_city_not_found(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([], 404),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 29.4,
                    'relative_humidity_2m' => 72,
                    'weather_code' => 1,
                    'wind_speed_10m' => 12.5,
                ],
                'current_units' => [
                    'temperature_2m' => '°C',
                    'wind_speed_10m' => 'km/h',
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/dashboard?city_search=UnknownPlace')
            ->assertOk()
            ->assertSee('Manila')
            ->assertSee('29.4')
            ->assertSee('Partly cloudy');
    }

    public function test_admin_dashboard_handles_valid_custom_city_search(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [[
                    'name' => 'Paris',
                    'latitude' => 48.8566,
                    'longitude' => 2.3522,
                ]],
            ]),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 18.5,
                    'relative_humidity_2m' => 64,
                    'weather_code' => 2,
                    'wind_speed_10m' => 9.1,
                ],
                'current_units' => [
                    'temperature_2m' => '°C',
                    'wind_speed_10m' => 'km/h',
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/dashboard?city_search=Paris')
            ->assertOk()
            ->assertSee('Paris')
            ->assertSee('18.5')
            ->assertSee('Partly cloudy');
    }

    public function test_admin_can_create_update_and_delete_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $create = $this->actingAs($admin)->post('/users', [
            'name' => 'New Staff',
            'email' => 'new.staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
        ]);

        $created = User::where('email', 'new.staff@example.com')->firstOrFail();
        $create->assertRedirect('/users');
        $this->assertSame('staff', $created->role);

        $update = $this->actingAs($admin)->put('/users/'.$created->id, [
            'name' => 'Updated Student',
            'email' => 'updated.student@example.com',
            'password' => '',
            'role' => 'student',
        ]);

        $update->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'role' => 'student',
            'email' => 'updated.student@example.com',
        ]);

        $delete = $this->actingAs($admin)->delete('/users/'.$created->id);

        $delete->assertRedirect('/users');
        $this->assertDatabaseMissing('users', ['id' => $created->id]);
    }

    public function test_only_admin_can_manage_account_and_profile_photos(): void
    {
        Storage::fake('public');
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($student)->get('/account')->assertOk();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Photo Staff',
            'email' => 'photo.staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'profile_photo' => UploadedFile::fake()->image('profile.png'),
        ]);

        $response->assertRedirect('/users');
        $created = User::where('email', 'photo.staff@example.com')->firstOrFail();
        $this->assertNotNull($created->profile_photo_path);
        Storage::disk('public')->assertExists($created->profile_photo_path);
    }

    public function test_staff_can_update_name_and_photo_but_not_password_or_role(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'old-password']);

        $response = $this->actingAs($staff)->put('/account', [
            'name' => 'Updated Staff',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'profile_photo' => UploadedFile::fake()->image('staff.png'),
        ]);

        $response->assertRedirect('/');
        $staff->refresh();
        $this->assertSame('Updated Staff', $staff->name);
        $this->assertSame('staff', $staff->role);
        $this->assertTrue(password_verify('old-password', $staff->password));
        Storage::disk('public')->assertExists($staff->profile_photo_path);
    }

    public function test_book_views_and_favorites_are_available_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $book = Book::create(['slug' => 'history-book', 'category' => 'book', 'title' => 'History Book', 'author' => 'Author', 'description' => 'Description', 'content' => 'Content']);

        $this->actingAs($student)->get('/reading/'.$book->slug)->assertOk();
        $this->actingAs($student)->post('/reading/'.$book->slug.'/favorite')->assertRedirect();
        $this->actingAs($admin)->get('/users/'.$student->id.'/history')->assertOk()->assertSee('History Book');
        $this->assertDatabaseHas('book_activities', ['user_id' => $student->id, 'book_id' => $book->id, 'type' => 'viewed']);
        $this->assertDatabaseHas('book_activities', ['user_id' => $student->id, 'book_id' => $book->id, 'type' => 'favorite']);
    }

    public function test_only_admins_can_record_staff_and_student_attendance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($student)->get('/attendance')->assertForbidden();

        $in = $this->actingAs($admin)->post('/attendance', [
            'user_id' => $staff->id,
            'action' => 'in',
        ]);

        $in->assertRedirect('/attendance');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $staff->id,
            'recorded_by' => $admin->id,
            'time_out' => null,
        ]);

        $out = $this->actingAs($admin)->post('/attendance', [
            'user_id' => $staff->id,
            'action' => 'out',
        ]);

        $out->assertRedirect('/attendance');
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $staff->id,
            'time_out' => null,
        ]);
    }

    public function test_logout_automatically_clocks_out_staff(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Attendance::clockIn($staff);

        $response = $this->actingAs($staff)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $staff->id,
            'time_out' => null,
        ]);
    }

    public function test_dashboard_has_an_animated_logout_transition_and_reduced_motion_bypass(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/staff/dashboard')
            ->assertOk()
            ->assertSee('logout-exit')
            ->assertSee('logging-out')
            ->assertSee('prefers-reduced-motion: reduce');
    }
}
