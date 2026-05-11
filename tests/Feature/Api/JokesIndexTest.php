<?php

declare(strict_types=1);

use App\Models\Joke;

it('эндпоинт доступен гостю и возвращает плоский JSON-массив', function () {
    Joke::factory()->count(3)->create();

    $response = $this->getJson(route('api.jokes.index'));

    $response->assertOk();

    expect($response->json())
        ->toBeArray()
        ->toHaveCount(3);
});

it('возвращает шутки в порядке убывания created_at', function () {
    $base = now()->subHour();
    $older = Joke::factory()->create(['created_at' => $base, 'updated_at' => $base]);
    $newer = Joke::factory()->create(['created_at' => $base->copy()->addMinutes(30), 'updated_at' => $base]);

    $response = $this->getJson(route('api.jokes.index'));

    $response->assertOk()
        ->assertJsonPath('0.id', $newer->id)
        ->assertJsonPath('1.id', $older->id);
});

it('возвращает пустой массив когда таблица пуста', function () {
    $response = $this->getJson(route('api.jokes.index'));

    $response->assertOk();

    expect($response->json())->toBe([]);
});

it('каждая запись содержит только поля JokeResource без external_id и updated_at', function () {
    Joke::factory()->create(['external_id' => 999]);

    $response = $this->getJson(route('api.jokes.index'));

    $response->assertOk()
        ->assertJsonStructure([
            ['id', 'type', 'setup', 'punchline', 'created_at'],
        ])
        ->assertJsonMissingPath('0.external_id')
        ->assertJsonMissingPath('0.updated_at');
});
