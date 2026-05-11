<?php

declare(strict_types=1);

namespace App\Data\Stats;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Timezone;
use Spatie\LaravelData\Data;

class GetHourlyVisitsData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(1)]
        public int $site_id,

        #[IntegerType, Min(1), Max(720)]
        public int $hours = 24,

        #[StringType, Timezone]
        public string $timezone = 'UTC',
    ) {}
}
