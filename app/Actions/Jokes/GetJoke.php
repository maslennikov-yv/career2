<?php

declare(strict_types=1);

namespace App\Actions\Jokes;

use App\Contracts\Actions\Jokes\GetsJoke;
use App\Data\Jokes\GetJokeData;
use App\Models\Joke;

class GetJoke implements GetsJoke
{
    public function __invoke(GetJokeData $data): Joke
    {
        return Joke::findOrFail($data->id);
    }
}
