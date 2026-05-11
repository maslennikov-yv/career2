<?php

declare(strict_types=1);

namespace App\Data\Sites;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class IndexSitesData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(1)]
        public int $user_id,
    ) {}
}
