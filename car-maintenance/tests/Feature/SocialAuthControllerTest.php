<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unsupported_provider_returns_404(): void
    {
        $this->get('/auth/twitter')->assertStatus(404);
    }

    public function test_redirect_routes_exist_for_supported_providers(): void
    {
        foreach (['google', 'facebook'] as $provider) {
            $response = $this->get("/auth/{$provider}");
            $this->assertContains($response->getStatusCode(), [302, 500]);
        }
    }

    public function test_oauth_callback_creates_new_user(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'id' => 'google_123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'nickname' => 'johndoe',
        ]);

        $this->mockSocialiteDriver('google', $socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(config('fortify.home'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'provider' => 'google',
            'provider_id' => 'google_123',
        ]);
        $this->assertAuthenticated();
    }

    public function test_oauth_callback_links_existing_user_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'provider' => null,
            'provider_id' => null,
        ]);

        $socialiteUser = $this->mockSocialiteUser([
            'id' => 'facebook_456',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'nickname' => 'janedoe',
        ]);

        $this->mockSocialiteDriver('facebook', $socialiteUser);

        $response = $this->get('/auth/facebook/callback');

        $response->assertRedirect(config('fortify.home'));
        $user->refresh();
        $this->assertEquals('facebook', $user->provider);
        $this->assertEquals('facebook_456', $user->provider_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_oauth_callback_logs_in_existing_oauth_user(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'provider' => 'google',
            'provider_id' => 'google_789',
        ]);

        $socialiteUser = $this->mockSocialiteUser([
            'id' => 'google_789',
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'nickname' => 'existing',
        ]);

        $this->mockSocialiteDriver('google', $socialiteUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(config('fortify.home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_oauth_callback_handles_failure_gracefully(): void
    {
        $driver = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $driver->shouldReceive('user')
            ->andThrow(new \Exception('Invalid authorization code'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($driver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['oauth']);
        $this->assertGuest();
    }

    public function test_oauth_callback_uses_nickname_when_name_is_null(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'id' => 'google_999',
            'name' => null,
            'email' => 'nickname@example.com',
            'nickname' => 'coolnick',
        ]);

        $this->mockSocialiteDriver('google', $socialiteUser);

        $this->get('/auth/google/callback')->assertRedirect(config('fortify.home'));
        $this->assertDatabaseHas('users', [
            'email' => 'nickname@example.com',
            'name' => 'coolnick',
        ]);
    }

    public function test_oauth_callback_uses_default_when_name_and_nickname_are_null(): void
    {
        $socialiteUser = $this->mockSocialiteUser([
            'id' => 'google_000',
            'name' => null,
            'email' => 'noname@example.com',
            'nickname' => null,
        ]);

        $this->mockSocialiteDriver('google', $socialiteUser);

        $this->get('/auth/google/callback')->assertRedirect(config('fortify.home'));
        $this->assertDatabaseHas('users', [
            'email' => 'noname@example.com',
            'name' => 'OAuth User',
        ]);
    }

    private function mockSocialiteUser(array $data): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getId')->andReturn($data['id']);
        $user->shouldReceive('getName')->andReturn($data['name']);
        $user->shouldReceive('getEmail')->andReturn($data['email']);
        $user->shouldReceive('getNickname')->andReturn($data['nickname']);

        return $user;
    }

    private function mockSocialiteDriver(string $provider, SocialiteUser $user): void
    {
        $driver = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $driver->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')
            ->with($provider)
            ->andReturn($driver);
    }
}
