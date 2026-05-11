<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Stats;

use App\Actions\Stats\GetCityBreakdown;
use App\Data\Stats\GetCityBreakdownData;
use Illuminate\Container\Attributes\Bind;

#[Bind(GetCityBreakdown::class)]
interface GetsCityBreakdown
{
    /**
     * @return array<int, array{city: string, visits: int}>
     */
    public function __invoke(GetCityBreakdownData $data): array;
}
