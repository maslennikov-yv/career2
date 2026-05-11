<?php

declare(strict_types=1);

use App\Contracts\Actions\Jokes\DeletesJoke;
use App\Data\Jokes\DeleteJokeData;
use App\Models\Joke;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('удаляет запись по id', function () {
    $joke = Joke::factory()->create();

    app(DeletesJoke::class)(DeleteJokeData::from(['id' => $joke->id]));

    $this->assertDatabaseMissing('jokes', ['id' => $joke->id]);
    expect(Joke::count())->toBe(0);
});

it('бросает ModelNotFoundException на несуществующий id', function () {
    app(DeletesJoke::class)(DeleteJokeData::from(['id' => 99_999]));
})->throws(ModelNotFoundException::class);

it('бросает ModelNotFoundException при повторном удалении той же записи', function () {
    $joke = Joke::factory()->create();
    $action = app(DeletesJoke::class);

    $action(DeleteJokeData::from(['id' => $joke->id]));
    $action(DeleteJokeData::from(['id' => $joke->id]));
})->throws(ModelNotFoundException::class);
