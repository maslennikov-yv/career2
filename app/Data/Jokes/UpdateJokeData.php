<?php

declare(strict_types=1);

namespace App\Data\Jokes;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateJokeData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(1)]
        public int $id,

        #[Sometimes, StringType, Min(1), Max(255)]
        public string|Optional $type,

        #[Sometimes, StringType, Min(1)]
        public string|Optional $setup,

        #[Sometimes, StringType, Min(1)]
        public string|Optional $punchline,

        #[Sometimes, Nullable, IntegerType, Min(1)]
        public int|null|Optional $external_id,
    ) {}
}
