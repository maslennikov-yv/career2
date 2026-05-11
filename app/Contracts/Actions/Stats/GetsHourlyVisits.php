<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Stats;

use App\Actions\Stats\GetHourlyVisits;
use App\Data\Stats\GetHourlyVisitsData;
use Illuminate\Container\Attributes\Bind;

#[Bind(GetHourlyVisits::class)]
interface GetsHourlyVisits
{
    /**
     * @return array<int, array{hour: string, uniques: int}>
     */
    public function __invoke(GetHourlyVisitsData $data): array;
}
