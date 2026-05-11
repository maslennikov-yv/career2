<?php

declare(strict_types=1);

namespace App\Actions\Jokes;

use App\Contracts\Actions\Jokes\UpdatesJoke;
use App\Data\Jokes\UpdateJokeData;
use App\Models\Joke;
use Illuminate\Support\Facades\DB;

class UpdateJoke implements UpdatesJoke
{
    public function __invoke(UpdateJokeData $data): Joke
    {
        return DB::transaction(function () use ($data): Joke {
            $joke = Joke::lockForUpdate()->findOrFail($data->id);

            $joke->update(collect($data->toArray())->except('id')->all());

            return $joke;
        });
    }
}
