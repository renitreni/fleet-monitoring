<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountPasswordReset;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_email_link_allows_a_single_password_change_for_the_signed_in_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $oldPassword = $user->password;
        $oldRememberToken = $user->remember_token;

        $this->actingAs($user)->post('/account/password/email', ['email' => $other->email])
            ->assertRedirect('/account')->assertSessionHas('passwordStatus');

        $this->assertSame($oldPassword, $user->fresh()->password);
        Notification::assertNotSentTo($other, AccountPasswordReset::class);
        $notification = Notification::sent($user, AccountPasswordReset::class)->sole();
        $url = $notification->toMail($user)->actionUrl;
        $this->get($url)->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page
            ->component('Account/Show')->where('passwordResetToken', $notification->token));

        $this->put('/account/password', $this->passwordData($notification->token))
            ->assertRedirect('/account')->assertSessionHas('passwordStatus', 'Your password has been changed.');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNotSame($oldRememberToken, $user->fresh()->remember_token);
        $this->put('/account/password', $this->passwordData($notification->token, 'another-password-456'))
            ->assertRedirect('/account')->assertSessionHas('passwordStatus', 'This verification link is invalid or expired. Please request a new email.');
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_invalid_expired_and_other_account_tokens_cannot_change_passwords(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $oldPassword = $user->password;
        $otherPassword = $other->password;
        $expiredToken = Password::createToken($user);
        $this->travel(61)->minutes();
        $otherToken = Password::createToken($other);

        foreach (['invalid-token', $otherToken, $expiredToken] as $token) {
            $this->actingAs($user)->get(route('account.show', ['token' => $token]))
                ->assertInertia(fn (Assert $page) => $page->where('passwordResetToken', null));
            $this->put('/account/password', [...$this->passwordData($token), 'email' => $other->email])
                ->assertRedirect('/account')->assertSessionHas('passwordStatus', 'This verification link is invalid or expired. Please request a new email.');
        }

        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->assertSame($otherPassword, $other->fresh()->password);
    }

    public function test_password_validation_preserves_the_token_for_retry(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $oldPassword = $user->password;

        foreach ([['password' => ''], ['password' => 'short'], ['password_confirmation' => 'mismatch']] as $invalid) {
            $this->actingAs($user)->from(route('account.show', ['token' => $token]))
                ->put('/account/password', [...$this->passwordData($token), ...$invalid])
                ->assertSessionHasErrors('password');
        }

        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_password_change_requires_a_token_and_cannot_use_the_old_endpoint(): void
    {
        $user = User::factory()->create();
        $oldPassword = $user->password;

        $this->actingAs($user)->put('/account/password', $this->passwordData(''))
            ->assertSessionHasErrorsIn('accountPassword', 'token');
        $this->put('/user/password', [...$this->passwordData(''), 'current_password' => 'password'])->assertNotFound();

        $this->assertSame($oldPassword, $user->fresh()->password);
    }

    public function test_google_users_can_set_a_password_after_email_verification(): void
    {
        $user = User::factory()->oAuth('google')->create();
        $token = Password::createToken($user);

        $this->actingAs($user)->put('/account/password', $this->passwordData($token))
            ->assertRedirect('/account')->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame('google', $user->fresh()->provider);
    }

    public function test_repeated_email_requests_are_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->post('/account/password/email')->assertSessionHasNoErrors();

        $this->post('/account/password/email')->assertSessionHasErrorsIn('passwordEmail', 'email');

        Notification::assertSentToTimes($user, AccountPasswordReset::class, 1);
    }

    public function test_resending_replaces_the_previous_verification_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->post('/account/password/email');
        $firstToken = Notification::sent($user, AccountPasswordReset::class)->sole()->token;
        $this->travel(61)->seconds();

        $this->post('/account/password/email')->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, AccountPasswordReset::class, 2);
        $latestToken = Notification::sent($user, AccountPasswordReset::class)->last()->token;
        $this->get(route('account.show', ['token' => $firstToken]))
            ->assertInertia(fn (Assert $page) => $page->where('passwordResetToken', null));
        $this->get(route('account.show', ['token' => $latestToken]))
            ->assertInertia(fn (Assert $page) => $page->where('passwordResetToken', $latestToken));
    }

    public function test_guests_cannot_request_or_change_account_passwords(): void
    {
        Notification::fake();

        $this->post('/account/password/email')->assertRedirect('/login');
        $this->put('/account/password', $this->passwordData('token'))->assertRedirect('/login');

        Notification::assertNothingSent();
    }

    /** @return array<string, string> */
    private function passwordData(string $token, string $password = 'new-password-123'): array
    {
        return ['token' => $token, 'password' => $password, 'password_confirmation' => $password];
    }
}
