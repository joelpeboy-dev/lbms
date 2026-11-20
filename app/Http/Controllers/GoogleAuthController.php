<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect');

        if (! is_string($clientId) || $clientId === '' || ! is_string($redirectUri) || $redirectUri === '') {
            return redirect()->route('login')->with('status', 'Google sign-in is not configured yet.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        $receivedState = $request->query('state');

        if (! is_string($expectedState) || ! is_string($receivedState) || ! hash_equals($expectedState, $receivedState)) {
            return redirect()->route('login')->with('status', 'Google sign-in could not be verified. Please try again.');
        }

        if ($request->query('error') !== null) {
            return redirect()->route('login')->with('status', 'Google sign-in was cancelled or not authorized.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect()->route('login')->with('status', 'Google did not return an authorization code. Please try again.');
        }

        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '' || ! is_string($redirectUri) || $redirectUri === '') {
            return redirect()->route('login')->with('status', 'Google sign-in is not configured yet.');
        }

        try {
            $tokenResponse = Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ])
                ->throw();

            $accessToken = $tokenResponse->json('access_token');

            if (! is_string($accessToken) || $accessToken === '') {
                return redirect()->route('login')->with('status', 'Google sign-in failed. Please try again.');
            }

            $profile = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            return redirect()->route('login')->with('status', 'Google could not be reached. Please try again.');
        }

        $googleId = is_array($profile) ? ($profile['sub'] ?? null) : null;
        $email = is_array($profile) ? ($profile['email'] ?? null) : null;
        $name = is_array($profile) ? ($profile['name'] ?? null) : null;
        $emailVerified = is_array($profile) ? ($profile['email_verified'] ?? false) : false;

        if (! is_string($googleId) || $googleId === '' || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || ! in_array($emailVerified, [true, 'true'], true) || ! is_string($name) || trim($name) === '') {
            return redirect()->route('login')->with('status', 'Google did not provide a verified email profile. Please try another account.');
        }

        $user = User::query()->where('google_id', $googleId)->first();

        if (! $user) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        }

        if ($user && $user->google_id !== null && $user->google_id !== $googleId) {
            return redirect()->route('login')->with('status', 'This account is linked to a different Google account. Please use the linked account.');
        }

        if (! $user) {
            $user = User::create([
                'name' => trim($name),
                'email' => strtolower($email),
                'password' => Str::random(64),
                'google_id' => $googleId,
                'role' => 'student',
            ]);
        }

        $user->forceFill([
            'google_id' => $googleId,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();
        Attendance::clockIn($user);
        $request->session()->put('welcome_animation_user_id', $user->id);

        return redirect('/'.$user->role.'/dashboard');
    }
}
