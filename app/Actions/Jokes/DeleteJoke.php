<?php

declare(strict_types=1);

namespace App\Actions\Jokes;

use App\Contracts\Actions\Jokes\DeletesJoke;
use App\Data\Jokes\DeleteJokeData;
use App\Models\Joke;

class DeleteJoke implements DeletesJoke
{
    public function __invoke(DeleteJokeData $data): void
    {
        Joke::findOrFail($data->id)->delete();
    }
}
