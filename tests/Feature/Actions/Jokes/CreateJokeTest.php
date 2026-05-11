<?php

declare(strict_types=1);

use App\Contracts\Actions\Jokes\CreatesJoke;
use App\Data\Jokes\CreateJokeData;
use App\Models\Joke;
use Illuminate\Validation\ValidationException;

it('создаёт запись со всеми полями включая external_id', function () {
    $joke = app(CreatesJoke::class)(CreateJokeData::from([
        'external_id' => 100,
        'type' => 'general',
        'setup' => 'setup-1',
        'punchline' => 'punchline-1',
    ]));

    expect($joke)
        ->toBeInstanceOf(Joke::class)
        ->external_id->toBe(100)
        ->type->toBe('general')
        ->setup->toBe('setup-1')
        ->punchline->toBe('punchline-1');

    $this->assertDatabaseHas('jokes', [
        'id' => $joke->id,
        'external_id' => 100,
    ]);
});

it('создаёт запись без external_id', function () {
    $joke = app(CreatesJoke::class)(CreateJokeData::from([
        'type' => 'knock-knock',
        'setup' => 's',
        'punchline' => 'p',
    ]));

    expect($joke->external_id)->toBeNull();
    $this->assertDatabaseCount('jokes', 1);
});

it('обновляет существующую запись при совпадении external_id (upsert)', function () {
    Joke::factory()->create([
        'external_id' => 42,
        'type' => 'old-type',
        'setup' => 'old-setup',
        'punchline' => 'old-punch',
    ]);

    $upserted = app(CreatesJoke::class)(CreateJokeData::from([
        'external_id' => 42,
        'type' => 'new-type',
        'setup' => 'new-setup',
        'punchline' => 'new-punch',
    ]));

    expect($upserted->type)->toBe('new-type');
    $this->assertDatabaseCount('jokes', 1);
    $this->assertDatabaseHas('jokes', [
        'external_id' => 42,
        'type' => 'new-type',
        'setup' => 'new-setup',
    ]);
});

it('создаёт отдельные записи когда external_id равен null', function () {
    $action = app(CreatesJoke::class);

    $action(CreateJokeData::from(['type' => 't', 'setup' => 's1', 'punchline' => 'p1']));
    $action(CreateJokeData::from(['type' => 't', 'setup' => 's2', 'punchline' => 'p2']));

    $this->assertDatabaseCount('jokes', 2);
});

it('бросает ValidationException когда обязательные поля DTO отсутствуют', function () {
    CreateJokeData::from(['type' => 'general']);
})->throws(ValidationException::class);

it('магический fromArray маппит id из API-payload на external_id', function () {
    $data = CreateJokeData::from([
        'id' => 7,
        'type' => 't',
        'setup' => 's',
        'punchline' => 'p',
    ]);

    expect($data->external_id)->toBe(7);
});

it('предпочитает явный external_id над id если переданы оба', function () {
    $data = CreateJokeData::from([
        'id' => 7,
        'external_id' => 99,
        'type' => 't',
        'setup' => 's',
        'punchline' => 'p',
    ]);

    expect($data->external_id)->toBe(99);
});

it('бросает ValidationException на пустые обязательные строки', function (string $field) {
    $payload = ['type' => 't', 'setup' => 's', 'punchline' => 'p'];
    $payload[$field] = '';

    CreateJokeData::from($payload);
})->throws(ValidationException::class)->with(['type', 'setup', 'punchline']);

it('бросает ValidationException когда type длиннее 255 символов', function () {
    CreateJokeData::from([
        'type' => str_repeat('a', 256),
        'setup' => 's',
        'punchline' => 'p',
    ]);
})->throws(ValidationException::class);

it('бросает ValidationException когда external_id меньше 1', function (int $invalid) {
    CreateJokeData::from([
        'type' => 't',
        'setup' => 's',
        'punchline' => 'p',
        'external_id' => $invalid,
    ]);
})->throws(ValidationException::class)->with([0, -1]);
