<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Notifications\AccountPasswordReset;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AccountPasswordController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $status = Password::broker(config('fortify.passwords'))->sendResetLink(
            ['email' => $request->user()->email],
            function (User $user, string $token): void {
                $user->notify(new AccountPasswordReset($token));
            },
        );

        if ($status !== Password::ResetLinkSent) {
            return back()->withErrors(['email' => __($status)], 'passwordEmail');
        }

        return to_route('account.show')->with('passwordStatus', 'Verification email sent. Open the link in your email to choose a new password.');
    }

    public function update(Request $request, ResetUserPassword $resetPassword): RedirectResponse
    {
        $request->validateWithBag('accountPassword', ['token' => ['required', 'string']]);

        $status = Password::broker(config('fortify.passwords'))->reset([
            ...$request->only('token', 'password', 'password_confirmation'),
            'email' => $request->user()->email,
        ], function (User $user) use ($request, $resetPassword): void {
            $resetPassword->reset($user, $request->only('password', 'password_confirmation'));
            $user->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            return to_route('account.show')->with('passwordStatus', 'This verification link is invalid or expired. Please request a new email.');
        }

        $request->session()->regenerate();

        return to_route('account.show')->with('passwordStatus', 'Your password has been changed.');
    }
}
