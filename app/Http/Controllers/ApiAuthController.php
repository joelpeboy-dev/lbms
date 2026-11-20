<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class ApiAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'student',
            'is_admin' => false,
        ]);

        return $this->createTokenResponse($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'The supplied credentials are invalid.'], 401);
        }

        return $this->createTokenResponse($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        return response()->json(['message' => 'The API token has been revoked.']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->only([
                'id',
                'name',
                'email',
                'role',
                'is_admin',
                'profile_photo_path',
                'student_id',
                'first_name',
                'last_name',
                'course',
                'year_level',
            ]),
        ]);
    }

    private function createTokenResponse(User $user, int $status = 200): JsonResponse
    {
        $expiresAt = now()->addDays(30);
        $token = $user->createToken('api-token', ['*'], $expiresAt);

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_admin']),
        ], $status);
    }
}
