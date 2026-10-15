<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\BookLoan;
use App\Models\LibraryAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const ROLES = ['admin', 'student', 'staff'];

    public function index(): View
    {
        return view('users.index', ['users' => User::query()->latest()->get()]);
    }

    public function create(): View
    {
        return view('users.create', ['roles' => self::ROLES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $data['password'] = Hash::make($data['password']);
        if ($request->hasFile('profile_photo')) {
            $data['profile_photo_path'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }
        unset($data['profile_photo']);
        $user = User::create($data);
        LibraryAuditLog::record('user.created', $user, ['role' => $user->role]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user, 'roles' => self::ROLES]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:'.implode(',', self::ROLES)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
            ApiToken::query()->where('user_id', $user->id)->delete();
        } else {
            unset($data['password']);
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $data['profile_photo_path'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }
        unset($data['profile_photo']);

        $user->update($data);
        LibraryAuditLog::record('user.updated', $user, ['changed_fields' => array_keys($user->getChanges())]);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot delete your own account.');

        if (BookLoan::query()->where('borrower_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'A borrower with library loan history cannot be deleted.',
            ]);
        }

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
        LibraryAuditLog::record('user.deleted', $user, ['role' => $user->role, 'name' => $user->name]);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
