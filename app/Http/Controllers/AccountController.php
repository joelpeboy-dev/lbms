<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\BookActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(): View
    {
        $user = request()->user();

        return view('account.edit', [
            'user' => $user,
            'favorites' => BookActivity::query()->with('book')->where('user_id', $user->id)->where('type', 'favorite')->latest()->get(),
            'history' => BookActivity::query()->with('book')->where('user_id', $user->id)->where('type', 'viewed')->latest()->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($user->role === 'admin') {
            $data = array_merge($data, $request->validate([
                'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
                'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            ]));
        }

        $user->name = $data['name'];
        if ($user->role === 'admin') {
            $user->email = $data['email'];
        }

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
            $user->tokens()->delete();
            ApiToken::query()->where('user_id', $user->id)->delete();
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $user->save();

        return back()->with('success', 'Account updated successfully.');
    }
}
