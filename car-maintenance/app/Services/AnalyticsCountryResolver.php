<?php

namespace App\Services;

use MaxMind\Db\Reader;
use Throwable;

class AnalyticsCountryResolver
{
    public function resolve(?string $ip): ?string
    {
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $path = config('analytics.country_database_path');

        if (! is_string($path) || ! is_readable($path)) {
            return null;
        }

        $reader = null;

        try {
            $reader = new Reader($path);
            $code = $reader->get($ip)['country']['iso_code'] ?? null;

            if (! is_string($code)) {
                return null;
            }

            $code = strtoupper($code);

            return preg_match('/^[A-Z]{2}$/D', $code) && ! in_array($code, ['XX', 'ZZ'], true) ? $code : null;
        } catch (Throwable) {
            return null;
        } finally {
            $reader?->close();
        }
    }
}
