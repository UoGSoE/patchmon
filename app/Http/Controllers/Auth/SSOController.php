<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Features\SupportRedirects\Redirector as LivewireRedirector;

class SSOController extends Controller
{
    public function login() : View|RedirectResponse|LivewireRedirector
    {
        if (Auth::check()) {
            return $this->getSuccessRedirect();
        }

        return view('auth.login');
    }

    public function loggedOut() : View
    {
        return view('auth.logged_out');
    }

    public function localLogin(Request $request) : RedirectResponse|LivewireRedirector
    {
        if (config('sso.enabled', true)) {
            abort(403, 'SSO is enabled');
        }

        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $throttleKey = sprintf('local-login:%s|%s', strtolower((string) $request->input('username')), $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return redirect()->back()->withErrors([
                'username' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (auth()->attempt($request->only('username', 'password'))) {
            RateLimiter::clear($throttleKey);

            return $this->getSuccessRedirect();
        }

        RateLimiter::hit($throttleKey, 300);

        return redirect()->back()->withErrors(['username' => 'Invalid credentials']);
    }

    public function ssoLogin() : RedirectResponse|LivewireRedirector
    {
        if (config('sso.enabled', true)) {
            $driver = Socialite::driver('keycloak');

            if (request()->hasSession() && session('force_reauth')) {
                $driver = $driver->with(['max_age' => 0]);
                session()->forget('force_reauth');
            }

            return $driver->redirect();
        }

        return redirect()->route('login.local');
    }

    public function handleProviderCallback(): RedirectResponse|LivewireRedirector
    {
        try {
            $ssoUser = Socialite::driver('keycloak')->user();
        } catch (\Exception $e) {
            Log::error('SSO callback failed', ['error' => $e->getMessage()]);
            abort(403, 'Authentication failed');
        }

        if ($this->forbidsStudentsFromLoggingIn($ssoUser)) {
            Log::warning('Denying student login attempt', ['email' => $ssoUser->email]);
            abort(403, 'Students are not allowed to login');
        }

        $ssoDetails = $this->getSSODetails($ssoUser);

        $user = User::where('email', $ssoDetails['email'])->first();

        if ($this->onlyAdminsCanLogin($user)) {
            Log::warning('Denying login attempt by non-admin', ['email' => $ssoDetails['email']]);
            abort(403, 'Only admins can login');
        }

        if (!$user && $this->shouldCreateNewUsers()) {
            $user = $this->createUser($ssoDetails);
        }

        if (!$user) {
            Log::warning('Denying login attempt for unknown user', ['email' => $ssoDetails['email']]);
            abort(403, 'Authentication failed');
        }

        Auth::login($user, false);
        session()->regenerate();

        return $this->getSuccessRedirect();
    }

    public function logout(Request $request): RedirectResponse|LivewireRedirector
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('force_reauth', true);

        return redirect()->route('logged_out');
    }

    private function getSuccessRedirect(): RedirectResponse|LivewireRedirector
    {
        if (Route::has('home')) {
            return redirect()->intended(route('home'));
        }

        return redirect()->intended(url('/'));
    }

    private function forbidsStudentsFromLoggingIn(\Laravel\Socialite\Contracts\User $ssoUser): bool
    {
        return $this->isStudent($ssoUser) && !config('sso.allow_students', true);
    }

    private function onlyAdminsCanLogin(?User $user): bool
    {
        return config('sso.admins_only', false) && (!$user || !$user->is_admin);
    }

    private function shouldCreateNewUsers(): bool
    {
        return config('sso.autocreate_new_users', false);
    }

    private function getSSODetails(\Laravel\Socialite\Contracts\User $ssoUser): array
    {
        return [
            'email' => strtolower(trim($ssoUser->email)),
            'username' => strtolower(trim($ssoUser->nickname)),
            'surname' => trim(data_get($ssoUser->user, 'family_name', '')),
            'forenames' => trim(data_get($ssoUser->user, 'given_name', '')),
            'is_staff' => $this->isStaff($ssoUser),
        ];
    }

    private function createUser(array $ssoDetails): User
    {
        return User::create([
            'password' => bcrypt(Str::random(64)),
            'username' => $ssoDetails['username'],
            'email' => $ssoDetails['email'],
            'surname' => $ssoDetails['surname'],
            'forenames' => $ssoDetails['forenames'],
            'is_staff' => $ssoDetails['is_staff'],
        ]);
    }

    private function isStudent(\Laravel\Socialite\Contracts\User $ssoUser): bool
    {
        return $this->looksLikeMatric($ssoUser->nickname);
    }

    private function isStaff(\Laravel\Socialite\Contracts\User $ssoUser): bool
    {
        return !$this->looksLikeMatric($ssoUser->nickname);
    }

    private function looksLikeMatric(string $username): bool
    {
        // Matric numbers are 7 digits (for now), followed by a letter
        return preg_match('/^[0-9]{7}[a-z]$/i', strtolower(trim($username))) === 1;
    }
}
