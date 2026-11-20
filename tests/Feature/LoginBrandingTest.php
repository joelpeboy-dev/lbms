<?php

namespace Tests\Feature;

use App\Models\LoginPageSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoginBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_default_picture_at_the_top_of_the_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="login-banner"', false)
            ->assertSee('images/library-login-art.svg');
    }

    public function test_only_admin_can_change_the_login_page_picture(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($staff)->get(route('admin.login-branding'))->assertForbidden();
        $this->actingAs($student)->put(route('admin.login-branding.update'), [
            'login_image' => UploadedFile::fake()->image('banner.png', 1200, 500),
        ])->assertForbidden();
        $this->assertDatabaseCount('login_page_settings', 0);
    }

    public function test_admin_can_upload_replace_and_reset_the_login_picture(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.login-branding'))
            ->assertOk()
            ->assertSee('Upload a new login picture');

        $this->put(route('admin.login-branding.update'), [
            'login_image' => UploadedFile::fake()->image('first-banner.png', 1200, 500),
        ])->assertRedirect(route('admin.login-branding'))
            ->assertSessionHas('success');

        $setting = LoginPageSetting::query()->firstOrFail();
        $this->assertSame(LoginPageSetting::SINGLETON_ID, $setting->id);
        $firstPath = $setting->image_path;
        Storage::disk('public')->assertExists($firstPath);
        $this->get(route('login'))->assertSee(Storage::disk('public')->url($firstPath));

        $this->put(route('admin.login-branding.update'), [
            'login_image' => UploadedFile::fake()->image('second-banner.webp', 1200, 500),
        ])->assertRedirect(route('admin.login-branding'));

        $secondPath = LoginPageSetting::query()->firstOrFail()->image_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->get(route('login'))->assertSee(Storage::disk('public')->url($secondPath));

        $this->put(route('admin.login-branding.update'), ['remove_image' => '1'])
            ->assertRedirect(route('admin.login-branding'))
            ->assertSessionHas('success');

        $this->assertNull(LoginPageSetting::query()->firstOrFail()->image_path);
        Storage::disk('public')->assertMissing($secondPath);
        $this->get(route('login'))->assertSee('images/library-login-art.svg');
    }

    public function test_admin_upload_must_be_an_approved_image_within_the_size_limit(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.login-branding.update'), [
            'login_image' => UploadedFile::fake()->create('banner.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('login_image');

        $this->assertDatabaseCount('login_page_settings', 0);
        Storage::disk('public')->assertDirectoryEmpty('login-branding');
    }
}
