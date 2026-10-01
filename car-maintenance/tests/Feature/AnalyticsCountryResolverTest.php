<?php

namespace Tests\Feature;

use App\Services\AnalyticsCountryResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalyticsCountryResolverTest extends TestCase
{
    #[DataProvider('addresses')]
    public function test_local_database_resolves_only_public_known_addresses(?string $ip, ?string $expected): void
    {
        config(['analytics.country_database_path' => base_path('tests/Fixtures/GeoIP2-Country-Test.mmdb')]);

        $this->assertSame($expected, app(AnalyticsCountryResolver::class)->resolve($ip));
    }

    public function test_missing_database_returns_unknown(): void
    {
        config(['analytics.country_database_path' => base_path('tests/Fixtures/missing.mmdb')]);

        $this->assertNull(app(AnalyticsCountryResolver::class)->resolve('81.2.69.160'));
    }

    public function test_corrupt_database_returns_unknown(): void
    {
        config(['analytics.country_database_path' => base_path('composer.json')]);

        $this->assertNull(app(AnalyticsCountryResolver::class)->resolve('81.2.69.160'));
    }

    public static function addresses(): array
    {
        return [
            'IPv4' => ['81.2.69.160', 'GB'],
            'IPv6' => ['2001:218::', 'JP'],
            'unmapped' => ['8.8.8.8', null],
            'private IPv4' => ['192.168.1.2', null],
            'loopback' => ['127.0.0.1', null],
            'private IPv6' => ['fd00::1', null],
            'IPv6 loopback' => ['::1', null],
            'invalid' => ['not-an-ip', null],
            'missing' => [null, null],
        ];
    }
}
