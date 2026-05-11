<?php

declare(strict_types=1);

use App\Contracts\Actions\Jokes\ListsJokes;
use App\Data\Jokes\ListJokesData;
use App\Models\Joke;
use Illuminate\Validation\ValidationException;

it('возвращает шутки в порядке убывания created_at, затем id', function () {
    $base = now()->subMinutes(10);

    $older = Joke::factory()->create(['created_at' => $base, 'updated_at' => $base]);
    $newer = Joke::factory()->create(['created_at' => $base->copy()->addMinutes(5), 'updated_at' => $base]);
    $sameTimeButLargerId = Joke::factory()->create(['created_at' => $base->copy()->addMinutes(5), 'updated_at' => $base]);

    $page = app(ListsJokes::class)(ListJokesData::from([]));

    expect($page->items())
        ->toHaveCount(3)
        ->and($page->items()[0]->id)->toBe($sameTimeButLargerId->id)
        ->and($page->items()[1]->id)->toBe($newer->id)
        ->and($page->items()[2]->id)->toBe($older->id);
});

it('применяет per_page и отдаёт курсор для следующей страницы', function () {
    Joke::factory()->count(5)->create();

    $page = app(ListsJokes::class)(ListJokesData::from(['per_page' => 2]));

    expect($page->items())->toHaveCount(2);
    expect($page->nextCursor())->not->toBeNull();
});

it('двигается по cursor и возвращает следующие записи', function () {
    Joke::factory()->count(5)->sequence(
        fn ($s) => ['created_at' => now()->subMinutes(5 - $s->index)],
    )->create();

    $first = app(ListsJokes::class)(ListJokesData::from(['per_page' => 2]));
    $second = app(ListsJokes::class)(ListJokesData::from([
        'per_page' => 2,
        'cursor' => $first->nextCursor()->encode(),
    ]));

    $firstIds = collect($first->items())->pluck('id')->all();
    $secondIds = collect($second->items())->pluck('id')->all();

    expect(array_intersect($firstIds, $secondIds))->toBeEmpty();
    expect($second->items())->toHaveCount(2);
});

it('фильтрует через JokeFilter по id_after и возвращает только более новые', function () {
    [$a, $b, $c, $d] = Joke::factory()->count(4)->create()->all();

    $result = app(ListsJokes::class)(ListJokesData::from(['after' => $b->id]));

    $ids = collect($result->items())->pluck('id')->all();

    expect($ids)
        ->toContain($c->id, $d->id)
        ->not->toContain($a->id, $b->id);
});

it('бросает ValidationException при per_page вне диапазона', function () {
    ListJokesData::from(['per_page' => 100]);
})->throws(ValidationException::class);

it('бросает ValidationException при отрицательном after', function () {
    ListJokesData::from(['after' => 0]);
})->throws(ValidationException::class);

it('возвращает пустой набор когда таблица пуста', function () {
    $page = app(ListsJokes::class)(ListJokesData::from([]));

    expect($page->items())->toBeEmpty()
        ->and($page->nextCursor())->toBeNull();
});
