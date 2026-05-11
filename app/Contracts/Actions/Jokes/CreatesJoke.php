<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Jokes;

use App\Actions\Jokes\CreateJoke;
use App\Data\Jokes\CreateJokeData;
use App\Models\Joke;
use Illuminate\Container\Attributes\Bind;

#[Bind(CreateJoke::class)]
interface CreatesJoke
{
    public function __invoke(CreateJokeData $data): Joke;
}
