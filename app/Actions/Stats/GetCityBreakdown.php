<?php

declare(strict_types=1);

namespace App\Actions\Stats;

use App\Contracts\Actions\Stats\GetsCityBreakdown;
use App\Data\Stats\GetCityBreakdownData;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GetCityBreakdown implements GetsCityBreakdown
{
    public function __invoke(GetCityBreakdownData $data): array
    {
        $since = Carbon::now()->subHours($data->hours);

        $rows = Visit::query()
            ->where('site_id', $data->site_id)
            ->where('occurred_at', '>=', $since)
            ->select(
                DB::raw("coalesce(city, 'Неизвестно') as city"),
                DB::raw('count(*) as visits'),
            )
            ->groupBy('city')
            ->orderByDesc('visits')
            ->get();

        $top = $rows->take($data->top);
        $rest = $rows->slice($data->top);

        $result = $top
            ->map(fn ($row) => [
                'city' => (string) $row->city,
                'visits' => (int) $row->visits,
            ])
            ->values()
            ->all();

        if ($rest->isNotEmpty()) {
            $result[] = [
                'city' => 'Прочее',
                'visits' => (int) $rest->sum('visits'),
            ];
        }

        return $result;
    }
}
