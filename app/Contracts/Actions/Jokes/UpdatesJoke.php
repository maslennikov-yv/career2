<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Jokes;

use App\Actions\Jokes\UpdateJoke;
use App\Data\Jokes\UpdateJokeData;
use App\Models\Joke;
use Illuminate\Container\Attributes\Bind;

#[Bind(UpdateJoke::class)]
interface UpdatesJoke
{
    public function __invoke(UpdateJokeData $data): Joke;
}
