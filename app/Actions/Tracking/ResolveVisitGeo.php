<?php

declare(strict_types=1);

namespace App\Actions\Tracking;

use App\Contracts\Actions\Tracking\ResolvesVisitGeo;
use App\Data\Tracking\ResolveVisitGeoData;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResolveVisitGeo implements ResolvesVisitGeo
{
    private const CACHE_TTL_SECONDS = 86_400;

    private const FIELDS = 'status,message,countryCode,country,regionName,city';

    public function __invoke(ResolveVisitGeoData $data): void
    {
        $visit = Visit::find($data->visit_id);

        if ($visit === null || $visit->geo_resolved_at !== null) {
            return;
        }

        $ip = (string) $visit->ip;

        if ($this->isPrivateIp($ip)) {
            $visit->update(['geo_resolved_at' => Carbon::now()]);

            return;
        }

        $geo = $this->lookup($ip);

        $visit->update([
            'country_code' => $geo['country_code'] ?? null,
            'country' => $geo['country'] ?? null,
            'region' => $geo['region'] ?? null,
            'city' => $geo['city'] ?? null,
            'geo_resolved_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<string, ?string>
     */
    private function lookup(string $ip): array
    {
        $cacheKey = "geoip:{$ip}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $resolved = $this->fetch($ip);

        if ($resolved !== null) {
            Cache::put($cacheKey, $resolved, self::CACHE_TTL_SECONDS);

            return $resolved;
        }

        return [];
    }

    /**
     * Возвращает данные при успехе и null при ошибке (чтобы не отравлять кэш на 24 часа).
     *
     * @return array<string, ?string>|null
     */
    private function fetch(string $ip): ?array
    {
        try {
            $response = Http::timeout(3)
                ->retry(2, 250)
                ->acceptJson()
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => self::FIELDS,
                    'lang' => 'ru',
                ]);
        } catch (\Throwable $e) {
            Log::warning('GeoIP lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'success') {
            return null;
        }

        return [
            'country_code' => $payload['countryCode'] ?? null,
            'country' => $payload['country'] ?? null,
            'region' => $payload['regionName'] ?? null,
            'city' => $payload['city'] ?? null,
        ];
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
