<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use RuntimeException;
use Throwable;

#[Signature('analytics:update-country-database')]
#[Description('Install the current monthly DB-IP Country Lite database for local analytics lookups')]
class UpdateAnalyticsCountryDatabase extends Command
{
    public function handle(): int
    {
        $path = config('analytics.country_database_path');
        $release = now()->format('Y-m');
        $temporaryPath = null;
        $lock = null;

        try {
            File::ensureDirectoryExists(dirname($path));
            $lock = fopen($path.'.lock', 'c');

            if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Cannot acquire database update lock.');
            }

            $currentDatabaseIsValid = false;

            if (is_readable($path) && is_readable($path.'.release') && trim(File::get($path.'.release')) === $release) {
                try {
                    $this->validateDatabase($path);
                    $currentDatabaseIsValid = true;
                } catch (Throwable) {
                    $currentDatabaseIsValid = false;
                }
            }

            if ($currentDatabaseIsValid) {
                $this->info('Country database is already up to date.');

                return self::SUCCESS;
            }

            $response = Http::connectTimeout(10)->timeout(120)
                ->get("https://download.db-ip.com/free/dbip-country-lite-{$release}.mmdb.gz");

            if ($response->notFound() && ! is_readable($path)) {
                $release = now()->startOfMonth()->subMonth()->format('Y-m');
                $response = Http::connectTimeout(10)->timeout(120)
                    ->get("https://download.db-ip.com/free/dbip-country-lite-{$release}.mmdb.gz");
            }

            $response->throw();

            if (strlen($response->body()) > 32 * 1024 * 1024) {
                throw new RuntimeException('Country database download is too large.');
            }

            $contents = @gzdecode($response->body(), 64 * 1024 * 1024);

            if ($contents === false || $contents === '') {
                throw new RuntimeException('Invalid compressed country database.');
            }

            $temporaryPath = tempnam(dirname($path), 'country-');

            if ($temporaryPath === false || File::put($temporaryPath, $contents) !== strlen($contents)) {
                throw new RuntimeException('Cannot write country database.');
            }

            $this->validateDatabase($temporaryPath);

            if (! chmod($temporaryPath, 0644) || ! rename($temporaryPath, $path)) {
                throw new RuntimeException('Cannot install country database.');
            }

            File::replace($path.'.release', $release);
            $this->info("Country database updated to {$release}.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::warning('Analytics country database update failed.', ['exception_type' => $exception::class]);
            $this->error('Country database update failed; any existing database was retained.');

            return self::FAILURE;
        } finally {
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                File::delete($temporaryPath);
            }

            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private function validateDatabase(string $path): void
    {
        $reader = new Reader($path);

        try {
            if (! str_contains(strtolower($reader->metadata()->databaseType), 'country')) {
                throw new RuntimeException('Expected a country database.');
            }

            $reader->get('8.8.8.8');
        } finally {
            $reader->close();
        }
    }
}
