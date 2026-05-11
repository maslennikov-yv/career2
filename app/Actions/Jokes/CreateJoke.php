<?php

declare(strict_types=1);

namespace App\Actions\Jokes;

use App\Contracts\Actions\Jokes\CreatesJoke;
use App\Data\Jokes\CreateJokeData;
use App\Models\Joke;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateJoke implements CreatesJoke
{
    public function __invoke(CreateJokeData $data): Joke
    {
        return DB::transaction(function () use ($data): Joke {
            $attributes = $data->toArray();

            if ($data->external_id === null) {
                return Joke::create($attributes);
            }

            try {
                return Joke::updateOrCreate(
                    ['external_id' => $data->external_id],
                    $attributes,
                );
            } catch (UniqueConstraintViolationException) {
                // Параллельный upsert уже вставил запись — обновляем существующую.
                $joke = Joke::where('external_id', $data->external_id)->firstOrFail();
                $joke->update($attributes);

                return $joke;
            }
        });
    }
}
