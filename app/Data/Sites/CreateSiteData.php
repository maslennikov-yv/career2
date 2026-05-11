<?php

declare(strict_types=1);

namespace App\Data\Sites;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class CreateSiteData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(1)]
        public int $user_id,

        #[Required, StringType, Min(1), Max(120)]
        public string $name,

        #[Nullable, StringType, Max(255)]
        public ?string $domain = null,
    ) {}
}
