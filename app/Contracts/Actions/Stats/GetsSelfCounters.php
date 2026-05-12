<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Stats;

use App\Actions\Stats\GetSelfCounters;
use App\Data\Stats\GetSelfCountersData;
use Illuminate\Container\Attributes\Bind;

#[Bind(GetSelfCounters::class)]
interface GetsSelfCounters
{
    /**
     * @return array{visits: int, uniques: int}
     */
    public function __invoke(GetSelfCountersData $data): array;
}
