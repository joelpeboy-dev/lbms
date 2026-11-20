<?php

namespace App\Http\Controllers;

use App\Models\LibraryAuditLog;
use App\Models\LoginPageSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LoginBrandingController extends Controller
{
    public function edit(): View
    {
        return view('admin.login-branding', [
            'setting' => LoginPageSetting::query()->find(LoginPageSetting::SINGLETON_ID),
            'defaultImage' => asset('images/library-login-art.svg'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login_image' => [
                'nullable',
                'required_without:remove_image',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp',
                'dimensions:max_width=2400,max_height=1600',
                'max:5120',
            ],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $setting = LoginPageSetting::query()->find(LoginPageSetting::SINGLETON_ID);
        $oldPath = $setting?->image_path;
        $removeImage = (bool) ($data['remove_image'] ?? false);

        if ($removeImage) {
            $imagePath = null;
        } elseif ($request->hasFile('login_image')) {
            $imagePath = $request->file('login_image')->store('login-branding', 'public');

            if (! is_string($imagePath)) {
                return back()->withErrors(['login_image' => 'The new login image could not be saved.']);
            }
        } else {
            return back()->withErrors(['login_image' => 'Choose an image or select the option to restore the default image.']);
        }

        if ($setting === null) {
            $setting = new LoginPageSetting;
            $setting->id = LoginPageSetting::SINGLETON_ID;
        }

        $setting->image_path = $imagePath;
        $setting->updated_by = $request->user()->id;
        $setting->save();

        if ($oldPath !== null && $oldPath !== $imagePath && ! Storage::disk('public')->delete($oldPath)) {
            return redirect()->route('admin.login-branding')
                ->withErrors(['login_image' => 'The login image was changed, but the previous image could not be removed from storage.']);
        }

        LibraryAuditLog::record(
            $removeImage ? 'login_image.reset' : 'login_image.updated',
            $setting,
            ['custom_image' => $imagePath !== null],
        );

        return redirect()->route('admin.login-branding')->with('success', $removeImage
            ? 'The default login image has been restored.'
            : 'The login image has been updated.');
    }
}
