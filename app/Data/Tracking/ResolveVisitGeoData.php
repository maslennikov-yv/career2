<?php

declare(strict_types=1);

namespace App\Data\Tracking;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ResolveVisitGeoData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(1)]
        public int $visit_id,
    ) {}
}
