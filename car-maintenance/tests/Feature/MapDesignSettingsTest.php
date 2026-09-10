<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MapDesignSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_and_change_map_settings(): void
    {
        $payload = ['enabled' => ['dark'], 'default' => 'dark'];
        $this->get('/admin/map-settings')->assertRedirect('/login');
        $this->put('/admin/map-settings', $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/map-settings')->assertForbidden();
        $this->put('/admin/map-settings', $payload)->assertForbidden();
        $this->assertDatabaseCount('map_design_settings', 0);
    }

    public function test_admin_sees_initial_designs_and_can_save_settings_for_everyone(): void
    {
        $this->actingAs(User::factory()->tripAdmin()->create());
        $this->get('/admin/map-settings')->assertInertia(fn (Assert $page) => $page
            ->component('MapSettings/Show')
            ->where('settings.enabled', ['standard', 'light', 'dark'])
            ->where('settings.default', 'standard')
            ->has('catalog', 3)
            ->where('updateUrl', route('admin.map-settings.update')));

        $this->put('/admin/map-settings', ['enabled' => ['light', 'dark'], 'default' => 'dark'])
            ->assertRedirect('/admin/map-settings')->assertSessionHasNoErrors();
        $settings = DB::table('map_design_settings')->find(1);
        $this->assertSame('dark', $settings->default_design);
        $this->assertSame(['light', 'dark'], json_decode($settings->enabled, true));
        $this->actingAs(User::factory()->create())->get('/routes')->assertInertia(fn (Assert $page) => $page
            ->where('mapDesigns.default', 'dark')
            ->has('mapDesigns.designs', 2)
            ->where('mapDesigns.designs.0.id', 'light')
            ->where('mapDesigns.designs.1.id', 'dark'));
    }

    public function test_admin_can_replace_settings_and_public_maps_use_the_saved_default(): void
    {
        $this->actingAs(User::factory()->tripAdmin()->create());
        $this->put('/admin/map-settings', ['enabled' => ['light', 'dark'], 'default' => 'dark'])->assertSessionHasNoErrors();
        $this->put('/admin/map-settings', ['enabled' => ['standard'], 'default' => 'standard'])->assertSessionHasNoErrors();
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
        $this->put('/admin/map-settings', ['enabled' => ['standard'], 'default' => 'standard'])->assertSessionHasNoErrors();

        $this->put('/admin/map-settings', $payload)->assertSessionHasErrors($field);

        $settings = DB::table('map_design_settings')->find(1);
        $this->assertSame('standard', $settings->default_design);
        $this->assertSame(['standard'], json_decode($settings->enabled, true));
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
