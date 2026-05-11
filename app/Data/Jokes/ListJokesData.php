<?php

declare(strict_types=1);

namespace App\Data\Jokes;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class ListJokesData extends Data
{
    public function __construct(
        #[Nullable, StringType]
        public ?string $cursor = null,

        #[Nullable, IntegerType, Min(1)]
        public ?int $after = null,

        #[IntegerType, Min(1), Max(50)]
        public int $per_page = 20,
    ) {}

    /**
     * @return array<string, int>
     */
    public function filterInput(): array
    {
        return $this->after === null ? [] : ['id_after' => $this->after];
    }
}
