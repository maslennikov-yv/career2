<?php

declare(strict_types=1);

namespace App\Actions\Stats;

use App\Contracts\Actions\Stats\GetsHourlyVisits;
use App\Data\Stats\GetHourlyVisitsData;
use App\Models\Visit;
use DateTimeZone;
use Illuminate\Support\Carbon;

class GetHourlyVisits implements GetsHourlyVisits
{
    private const KEY_FORMAT = 'Y-m-d\TH:i:00';

    public function __invoke(GetHourlyVisitsData $data): array
    {
        $tz = new DateTimeZone($data->timezone);

        $until = Carbon::now($tz)->startOfHour()->addHour();
        $since = $until->copy()->subHours($data->hours);

        $rows = Visit::query()
            ->where('site_id', $data->site_id)
            ->whereBetween('occurred_at', [$since, $until])
            ->selectRaw(
                "to_char(date_trunc('hour', occurred_at AT TIME ZONE ?), 'YYYY-MM-DD\"T\"HH24:MI:00') as hour, count(distinct visitor_uid) as uniques",
                [$tz->getName()],
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy(fn ($row) => $row->hour);

        $points = [];

        for ($cursor = $since->copy(); $cursor->lt($until); $cursor = $cursor->addHour()) {
            $key = $cursor->format(self::KEY_FORMAT);
            $points[] = [
                'hour' => $key,
                'uniques' => (int) ($rows[$key]->uniques ?? 0),
            ];
        }

        return $points;
    }
}
