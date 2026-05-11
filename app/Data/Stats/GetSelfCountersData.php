<?php

declare(strict_types=1);

namespace App\Data\Stats;

use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class GetSelfCountersData extends Data
{
    public const PUBLIC_ID_PATTERN = '/^[a-z0-9]{16}$/';

    public function __construct(
        #[Required, StringType, Regex(self::PUBLIC_ID_PATTERN)]
        public string $public_id,
    ) {}
}
