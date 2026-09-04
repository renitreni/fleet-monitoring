<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialAuthController extends Controller
{
    /**
     * Supported OAuth providers.
     */
    private const ALLOWED_PROVIDERS = ['google', 'facebook'];

    /**
     * Redirect the user to the OAuth provider authentication page.
     */
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from the OAuth provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            Log::warning('OAuth authentication failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')
                ->withErrors(['oauth' => 'Unable to authenticate using '.ucfirst($provider).'. Please try again.']);
        }

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if (! $user) {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                ]);
            } else {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'OAuth User',
                    'email' => $socialUser->getEmail(),
                    'country' => 'US',
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'password' => null,
                    'email_verified_at' => now(),
                ]);
            }
        }

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home'))
            ->with('success', 'You have been logged in successfully.');
    }

    /**
     * Ensure the given provider is supported.
     *
     * @throws \InvalidArgumentException
     */
    private function ensureProviderIsAllowed(string $provider): void
    {
        if (! in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            abort(404, 'Unsupported OAuth provider.');
        }
    }
}
