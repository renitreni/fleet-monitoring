<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MapDesignSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_change_map_designs(): void
    {
        $payload = ['enabled' => ['dark'], 'default' => 'dark'];
        $this->put('/admin/map-designs', $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->put('/admin/map-designs', $payload)->assertForbidden();
        $this->assertDatabaseCount('map_design_settings', 0);
    }

    public function test_admin_sees_initial_designs_and_can_save_settings_for_everyone(): void
    {
        $this->actingAs(User::factory()->tripAdmin()->create());
        $this->get('/admin/trips')->assertInertia(fn (Assert $page) => $page
            ->where('mapDesignSettings.enabled', ['standard', 'light', 'dark'])
            ->where('mapDesignSettings.default', 'standard')
            ->has('mapDesignCatalog', 3));

        $this->put('/admin/map-designs', ['enabled' => ['light', 'dark'], 'default' => 'dark'])
            ->assertRedirect('/admin/trips')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('map_design_settings', ['id' => 1, 'default_design' => 'dark', 'enabled' => '["light","dark"]']);
        $this->actingAs(User::factory()->create())->get('/routes')->assertInertia(fn (Assert $page) => $page
            ->where('mapDesigns.default', 'dark')
            ->has('mapDesigns.designs', 2)
            ->where('mapDesigns.designs.0.id', 'light')
            ->where('mapDesigns.designs.1.id', 'dark'));
    }

    public function test_admin_can_replace_settings_and_public_maps_use_the_saved_default(): void
    {
        $this->actingAs(User::factory()->tripAdmin()->create());
        $this->put('/admin/map-designs', ['enabled' => ['light', 'dark'], 'default' => 'dark'])->assertSessionHasNoErrors();
        $this->put('/admin/map-designs', ['enabled' => ['standard'], 'default' => 'standard'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('map_design_settings', 1);
        $this->app['auth']->forgetGuards();
        $this->get('/routes')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('mapDesigns.default', 'standard')
            ->has('mapDesigns.designs', 1)
            ->where('mapDesigns.designs.0.id', 'standard'));
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_are_rejected_without_changing_saved_settings(array $payload, string $field): void
    {
        $this->actingAs(User::factory()->tripAdmin()->create());
        $this->put('/admin/map-designs', ['enabled' => ['standard'], 'default' => 'standard'])->assertSessionHasNoErrors();

        $this->put('/admin/map-designs', $payload)->assertSessionHasErrors($field);

        $this->assertDatabaseHas('map_design_settings', ['id' => 1, 'default_design' => 'standard', 'enabled' => '["standard"]']);
    }

    public static function invalidSettings(): array
    {
        return [
            'empty selection' => [['enabled' => [], 'default' => 'standard'], 'enabled'],
            'unknown design' => [['enabled' => ['satellite'], 'default' => 'satellite'], 'enabled.0'],
            'disabled default' => [['enabled' => ['light'], 'default' => 'dark'], 'default'],
            'duplicate design' => [['enabled' => ['light', 'light'], 'default' => 'light'], 'enabled.0'],
            'missing fields' => [[], 'default'],
            'malformed selection' => [['enabled' => 'dark', 'default' => 'dark'], 'enabled'],
            'non-string design' => [['enabled' => [['dark']], 'default' => 'dark'], 'enabled.0'],
        ];
    }
}
