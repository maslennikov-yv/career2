<?php

declare(strict_types=1);

use App\Contracts\Integrations\FetchesRandomJoke;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('services.random_joke.endpoint', 'https://example.test/random_joke');
    config()->set('services.random_joke.timeout', 30);
});

it('возвращает распарсенную шутку из ответа API', function () {
    Http::fake([
        'https://example.test/random_joke' => Http::response([
            'id' => 1,
            'type' => 'general',
            'setup' => 'Why did the chicken cross the road?',
            'punchline' => 'To get to the other side.',
        ]),
    ]);

    $joke = app(FetchesRandomJoke::class)();

    expect($joke)->toMatchArray([
        'id' => 1,
        'type' => 'general',
        'setup' => 'Why did the chicken cross the road?',
        'punchline' => 'To get to the other side.',
    ]);
});

it('делает GET-запрос с Accept: application/json на правильный endpoint', function () {
    Http::fake([
        'https://example.test/random_joke' => Http::response(['setup' => 'a', 'punchline' => 'b']),
    ]);

    app(FetchesRandomJoke::class)();

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://example.test/random_joke'
        && $request->hasHeader('Accept', 'application/json')
    );
});

it('бросает RequestException на ошибочный HTTP-статус', function () {
    Http::fake([
        'https://example.test/random_joke' => Http::response('boom', 503),
    ]);

    app(FetchesRandomJoke::class)();
})->throws(RequestException::class);

it('бросает RuntimeException на пустой body со статусом 200', function () {
    Http::fake([
        'https://example.test/random_joke' => Http::response('', 200),
    ]);

    app(FetchesRandomJoke::class)();
})->throws(RuntimeException::class, 'RandomJoke API вернул пустой или невалидный JSON.');

it('бросает RuntimeException когда endpoint не сконфигурирован', function () {
    config()->set('services.random_joke.endpoint', '');

    app(FetchesRandomJoke::class)();
})->throws(RuntimeException::class, 'services.random_joke.endpoint не сконфигурирован.');
