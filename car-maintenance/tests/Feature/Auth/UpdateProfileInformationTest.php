<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UpdateProfileInformationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_account_page_shows_the_name_change_allowance(): void
    {
        $user = User::factory()->create([
            'name_change_history' => [now()->subMonths(2)->toIso8601String()],
        ]);

        $this->actingAs($user)->get('/account')->assertInertia(fn (Assert $page) => $page
            ->component('Account/Show')
            ->where('nameChangeQuota.limit', 3)
            ->where('nameChangeQuota.used', 1)
            ->where('nameChangeQuota.remaining', 2)
            ->where('nameChangeQuota.next_available_at', null));
    }

    public function test_password_user_can_change_their_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Original Name']);

        $this->actingAs($user)->from('/account')->put('/user/profile-information', $this->profileData($user, 'Event Alias'))
            ->assertRedirect('/account')
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Event Alias', $user->name);
        $this->assertCount(1, $user->name_change_history);
    }

    public function test_google_user_can_change_their_display_name(): void
    {
        $user = User::factory()->oAuth('google')->create(['name' => 'Google Name']);

        $this->actingAs($user)->from('/account')->put('/user/profile-information', $this->profileData($user, 'Track Alias'))
            ->assertRedirect('/account')
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Track Alias', $user->name);
        $this->assertSame('google', $user->provider);
        $this->assertCount(1, $user->name_change_history);
    }

    public function test_fourth_name_change_within_twelve_months_is_rejected(): void
    {
        $this->travelTo('2026-09-09 10:00:00');
        $user = User::factory()->create(['name' => 'Original Name']);

        foreach (['First Name', 'Second Name', 'Third Name'] as $name) {
            $this->actingAs($user)->put('/user/profile-information', $this->profileData($user, $name))
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($user)->from('/account')->put('/user/profile-information', $this->profileData($user, 'Fourth Name'))
            ->assertRedirect('/account')
            ->assertSessionHasErrorsIn('updateProfileInformation', [
                'name' => 'You have used all three name changes for the last 12 months. You can change it again on Sep 9, 2027.',
            ]);

        $user->refresh();
        $this->assertSame('Third Name', $user->name);
        $this->assertCount(3, $user->name_change_history);
    }

    public function test_name_change_becomes_available_after_twelve_months(): void
    {
        $this->travelTo('2026-09-09 10:00:00');
        $user = User::factory()->create([
            'name' => 'Current Name',
            'name_change_history' => [
                '2025-09-08T10:00:00+00:00',
                '2026-01-10T10:00:00+00:00',
                '2026-04-10T10:00:00+00:00',
            ],
        ]);

        $this->actingAs($user)->from('/account')->put('/user/profile-information', $this->profileData($user, 'Available Name'))
            ->assertRedirect('/account')
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Available Name', $user->name);
        $this->assertCount(3, $user->name_change_history);
        $this->assertSame('2026-09-09T10:00:00+00:00', $user->name_change_history[2]);
    }

    public function test_saving_the_current_name_does_not_use_an_allowance(): void
    {
        $user = User::factory()->create(['name' => 'Same Name']);

        $this->actingAs($user)->put('/user/profile-information', $this->profileData($user, 'Same Name'))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->name_change_history);
    }

    public function test_display_name_is_required(): void
    {
        $user = User::factory()->create(['name' => 'Valid Name']);

        $this->actingAs($user)->from('/account')->put('/user/profile-information', $this->profileData($user, ''))
            ->assertRedirect('/account')
            ->assertSessionHasErrorsIn('updateProfileInformation', [
                'name' => 'The name field is required.',
            ]);

        $this->assertSame('Valid Name', $user->fresh()->name);
    }

    public function test_display_name_must_be_unique_ignoring_case_and_extra_spaces(): void
    {
        User::factory()->create(['name' => 'Taken Name']);
        $user = User::factory()->create(['name' => 'Current Name']);

        $this->actingAs($user)->from('/account')->put('/user/profile-information', $this->profileData($user, '  taken   name  '))
            ->assertRedirect('/account')
            ->assertSessionHasErrorsIn('updateProfileInformation', [
                'name' => 'That display name is already taken.',
            ]);

        $user->refresh();
        $this->assertSame('Current Name', $user->name);
        $this->assertNull($user->name_change_history);
    }

    public function test_guest_cannot_open_or_update_an_account(): void
    {
        $user = User::factory()->make();

        $this->get('/account')->assertRedirect('/login');
        $this->put('/user/profile-information', $this->profileData($user, 'Guest Name'))->assertRedirect('/login');
    }

    /** @return array{name: string, email: string, country: string} */
    private function profileData(User $user, string $name): array
    {
        return [
            'name' => $name,
            'email' => $user->email,
            'country' => $user->country,
        ];
    }
}
