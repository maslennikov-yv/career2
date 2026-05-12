<?php

declare(strict_types=1);

namespace App\Actions\Stats;

use App\Contracts\Actions\Stats\GetsSelfCounters;
use App\Data\Stats\GetSelfCountersData;
use App\Enums\VisitDeviceType;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Support\Facades\Cache;

class GetSelfCounters implements GetsSelfCounters
{
    private const CACHE_TTL_SECONDS = 30;

    public function __invoke(GetSelfCountersData $data): array
    {
        return Cache::remember(
            "counter:self:{$data->public_id}",
            self::CACHE_TTL_SECONDS,
            function () use ($data): array {
                $site = Site::query()->where('public_id', $data->public_id)->first();

                if ($site === null) {
                    return ['visits' => 0, 'uniques' => 0];
                }

                $row = Visit::query()
                    ->where('site_id', $site->id)
                    ->where('device_type', '!=', VisitDeviceType::Bot)
                    ->selectRaw('count(*) as visits, count(distinct visitor_uid) as uniques')
                    ->first();

                return [
                    'visits' => (int) $row->visits,
                    'uniques' => (int) $row->uniques,
                ];
            },
        );
    }
}
