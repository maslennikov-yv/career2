<?php

declare(strict_types=1);

use App\Contracts\Actions\Jokes\GetsJoke;
use App\Data\Jokes\GetJokeData;
use App\Models\Joke;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('возвращает запись по id', function () {
    $joke = Joke::factory()->create();

    $got = app(GetsJoke::class)(GetJokeData::from(['id' => $joke->id]));

    expect($got)
        ->toBeInstanceOf(Joke::class)
        ->id->toBe($joke->id)
        ->setup->toBe($joke->setup)
        ->punchline->toBe($joke->punchline);
});

it('бросает ModelNotFoundException на несуществующий id', function () {
    app(GetsJoke::class)(GetJokeData::from(['id' => 99_999]));
})->throws(ModelNotFoundException::class);
