<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MapDesigns
{
    public const CATALOG = [
        ['id' => 'standard', 'name' => 'Standard', 'description' => 'Original street map colors.', 'filter' => 'none'],
        ['id' => 'light', 'name' => 'Light', 'description' => 'Muted grayscale streets for a quieter background.', 'filter' => 'grayscale(1) brightness(1.08)'],
        ['id' => 'dark', 'name' => 'Dark', 'description' => 'Dark streets with bright route markers.', 'filter' => 'grayscale(1) invert(1) brightness(0.85)'],
    ];

    /** @return array{enabled: list<string>, default: string} */
    public function settings(): array
    {
        $settings = DB::table('map_design_settings')->where('id', 1)->first();

        return $settings
            ? ['enabled' => json_decode($settings->enabled, true, flags: JSON_THROW_ON_ERROR), 'default' => $settings->default_design]
            : ['enabled' => ['standard', 'light', 'dark'], 'default' => 'standard'];
    }

    /** @return array{designs: list<array<string, string>>, default: string} */
    public function available(): array
    {
        $settings = $this->settings();

        return [
            'designs' => array_values(array_filter(self::CATALOG, fn (array $design): bool => in_array($design['id'], $settings['enabled'], true))),
            'default' => $settings['default'],
        ];
    }
}
