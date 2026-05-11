<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Integrations\RandomJokeIntegration;
use Illuminate\Container\Attributes\Bind;

#[Bind(RandomJokeIntegration::class)]
interface FetchesRandomJoke
{
    /**
     * @return array{id?: int, type?: string, setup?: string, punchline?: string}
     */
    public function __invoke(): array;
}
