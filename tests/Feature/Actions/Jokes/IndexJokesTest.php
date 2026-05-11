<?php

declare(strict_types=1);

use App\Contracts\Actions\Jokes\IndexesJokes;
use App\Data\Jokes\IndexJokesData;
use App\Models\Joke;

it('возвращает все шутки в порядке убывания created_at, затем id', function () {
    $base = now()->subMinutes(10);

    $older = Joke::factory()->create(['created_at' => $base, 'updated_at' => $base]);
    $newer = Joke::factory()->create(['created_at' => $base->copy()->addMinutes(5), 'updated_at' => $base]);
    $sameTimeButLargerId = Joke::factory()->create(['created_at' => $base->copy()->addMinutes(5), 'updated_at' => $base]);

    $items = app(IndexesJokes::class)(new IndexJokesData);

    expect($items)
        ->toHaveCount(3)
        ->and($items[0]->id)->toBe($sameTimeButLargerId->id)
        ->and($items[1]->id)->toBe($newer->id)
        ->and($items[2]->id)->toBe($older->id);
});

it('возвращает пустую коллекцию когда таблица пуста', function () {
    $items = app(IndexesJokes::class)(new IndexJokesData);

    expect($items)->toBeEmpty();
});
