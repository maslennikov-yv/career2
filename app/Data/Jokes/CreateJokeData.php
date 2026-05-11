<?php

declare(strict_types=1);

namespace App\Data\Jokes;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class CreateJokeData extends Data
{
    public function __construct(
        #[Required, StringType, Min(1), Max(255)]
        public string $type,

        #[Required, StringType, Min(1)]
        public string $setup,

        #[Required, StringType, Min(1)]
        public string $punchline,

        #[Nullable, IntegerType, Min(1)]
        public ?int $external_id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): static
    {
        if (! array_key_exists('external_id', $payload) && array_key_exists('id', $payload)) {
            $payload['external_id'] = $payload['id'];
        }

        return static::factory()->withoutMagicalCreation()->from($payload);
    }
}
