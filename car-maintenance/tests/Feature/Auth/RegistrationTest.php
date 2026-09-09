<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register_and_land_on_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'country' => 'US',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'country' => 'US',
            'provider' => null,
        ]);
    }

    public function test_registration_requires_a_valid_country_code(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('country');
        $this->assertGuest();
    }

    public function test_new_users_are_email_verified_on_registration(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'country' => 'US',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_registration_rejects_a_display_name_that_is_already_taken(): void
    {
        User::factory()->create(['name' => 'Road Runner']);

        $this->post('/register', [
            'name' => 'road runner',
            'email' => 'another@example.com',
            'country' => 'US',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors([
            'name' => 'That display name is already taken.',
        ]);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'another@example.com']);
    }
}
