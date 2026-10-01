<?php

namespace Tests\Feature;

use App\Services\AnalyticsCountryResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateAnalyticsCountryDatabaseTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/analytics-country-'.bin2hex(random_bytes(8));
        config(['analytics.country_database_path' => $this->directory.'/country.mmdb']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_update_installs_valid_database_and_skips_installed_release(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        Http::preventStrayRequests();
        Http::fake([
            'https://download.db-ip.com/free/dbip-country-lite-2026-10.mmdb.gz' => Http::response(
                gzencode(File::get(base_path('tests/Fixtures/GeoIP2-Country-Test.mmdb')))
            ),
        ]);

        $this->artisan('analytics:update-country-database')
            ->expectsOutput('Country database updated to 2026-10.')
            ->assertSuccessful();
        $this->artisan('analytics:update-country-database')
            ->expectsOutput('Country database is already up to date.')
            ->assertSuccessful();

        $this->assertSame('GB', app(AnalyticsCountryResolver::class)->resolve('81.2.69.160'));
        $this->assertSame('2026-10', File::get($this->directory.'/country.mmdb.release'));
        Http::assertSentCount(1);
    }

    #[DataProvider('failedDownloads')]
    public function test_failed_update_retains_previous_database_and_can_retry(string $body, int $status): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        File::ensureDirectoryExists($this->directory);
        $original = File::get(base_path('tests/Fixtures/GeoIP2-Country-Test.mmdb'));
        File::put($this->directory.'/country.mmdb', $original);
        File::put($this->directory.'/country.mmdb.release', '2026-09');
        Http::preventStrayRequests();
        Http::fake([
            'https://download.db-ip.com/free/dbip-country-lite-2026-10.mmdb.gz' => Http::sequence()
                ->push($body, $status)
                ->push(gzencode($original)),
        ]);

        $this->artisan('analytics:update-country-database')->assertFailed();

        $this->assertSame($original, File::get($this->directory.'/country.mmdb'));
        $this->assertSame('2026-09', File::get($this->directory.'/country.mmdb.release'));
        $this->assertSame([], glob($this->directory.'/country-*'));

        $this->artisan('analytics:update-country-database')->assertSuccessful();

        $this->assertSame('2026-10', File::get($this->directory.'/country.mmdb.release'));
        Http::assertSentCount(2);
    }

    public function test_first_install_falls_back_to_previous_month_then_retries_current_release(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        $compressed = gzencode(File::get(base_path('tests/Fixtures/GeoIP2-Country-Test.mmdb')));
        Http::preventStrayRequests();
        Http::fake([
            'https://download.db-ip.com/free/dbip-country-lite-2026-10.mmdb.gz' => Http::sequence()
                ->push('', 404)->push($compressed),
            'https://download.db-ip.com/free/dbip-country-lite-2026-09.mmdb.gz' => Http::response($compressed),
        ]);

        $this->artisan('analytics:update-country-database')
            ->expectsOutput('Country database updated to 2026-09.')->assertSuccessful();

        $this->assertSame('2026-09', File::get($this->directory.'/country.mmdb.release'));
        $this->assertSame('GB', app(AnalyticsCountryResolver::class)->resolve('81.2.69.160'));

        $this->artisan('analytics:update-country-database')
            ->expectsOutput('Country database updated to 2026-10.')->assertSuccessful();

        $this->assertSame('2026-10', File::get($this->directory.'/country.mmdb.release'));
        Http::assertSentCount(3);
    }

    public function test_corrupt_current_release_is_replaced_instead_of_skipped(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        File::ensureDirectoryExists($this->directory);
        File::put($this->directory.'/country.mmdb', 'corrupt');
        File::put($this->directory.'/country.mmdb.release', '2026-10');
        Http::preventStrayRequests();
        Http::fake([
            'https://download.db-ip.com/free/dbip-country-lite-2026-10.mmdb.gz' => Http::response(
                gzencode(File::get(base_path('tests/Fixtures/GeoIP2-Country-Test.mmdb')))
            ),
        ]);

        $this->artisan('analytics:update-country-database')->assertSuccessful();

        $this->assertSame('GB', app(AnalyticsCountryResolver::class)->resolve('81.2.69.160'));
        Http::assertSentCount(1);
    }

    public static function failedDownloads(): array
    {
        return [
            'unavailable monthly release' => ['', 404],
            'invalid compression' => ['not gzip', 200],
            'invalid database' => [gzencode('not mmdb'), 200],
        ];
    }
}
