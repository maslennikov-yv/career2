<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Jokes;

use App\Actions\Jokes\GetJoke;
use App\Data\Jokes\GetJokeData;
use App\Models\Joke;
use Illuminate\Container\Attributes\Bind;

#[Bind(GetJoke::class)]
interface GetsJoke
{
    public function __invoke(GetJokeData $data): Joke;
}
