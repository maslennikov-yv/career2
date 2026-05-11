<?php

declare(strict_types=1);

use App\Contracts\Actions\Jokes\UpdatesJoke;
use App\Data\Jokes\UpdateJokeData;
use App\Models\Joke;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('обновляет только указанные поля и не трогает остальные', function () {
    $joke = Joke::factory()->create([
        'type' => 'general',
        'setup' => 'original-setup',
        'punchline' => 'original-punch',
    ]);

    $updated = app(UpdatesJoke::class)(UpdateJokeData::from([
        'id' => $joke->id,
        'type' => 'programming',
    ]));

    expect($updated->fresh())
        ->type->toBe('programming')
        ->setup->toBe('original-setup')
        ->punchline->toBe('original-punch');
});

it('обнуляет external_id когда явно передаётся null', function () {
    $joke = Joke::factory()->create(['external_id' => 999]);

    app(UpdatesJoke::class)(UpdateJokeData::from([
        'id' => $joke->id,
        'external_id' => null,
    ]));

    expect($joke->fresh()->external_id)->toBeNull();
});

it('ничего не меняет когда передан только id', function () {
    $joke = Joke::factory()->create([
        'type' => 't',
        'setup' => 's',
        'punchline' => 'p',
        'external_id' => 17,
    ]);
    $original = $joke->only(['type', 'setup', 'punchline', 'external_id']);

    app(UpdatesJoke::class)(UpdateJokeData::from(['id' => $joke->id]));

    expect($joke->fresh()->only(array_keys($original)))->toEqual($original);
});

it('бросает ModelNotFoundException на несуществующий id', function () {
    app(UpdatesJoke::class)(UpdateJokeData::from([
        'id' => 99_999,
        'type' => 'whatever',
    ]));
})->throws(ModelNotFoundException::class);
