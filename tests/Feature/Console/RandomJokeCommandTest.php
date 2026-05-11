<?php

declare(strict_types=1);

use App\Models\Joke;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config()->set('services.random_joke.endpoint', 'https://example.test/random_joke');
    config()->set('services.random_joke.timeout', 30);
});

it('сохраняет полученную шутку в БД и печатает setup и punchline', function () {
    Http::fake([
        'https://example.test/random_joke' => Http::response([
            'id' => 42,
            'type' => 'programming',
            'setup' => 'Why do programmers prefer dark mode?',
            'punchline' => 'Because light attracts bugs.',
        ]),
    ]);

    $this->artisan('app:random-joke')
        ->expectsOutput('Why do programmers prefer dark mode?')
        ->expectsOutput('Because light attracts bugs.')
        ->assertSuccessful();

    $this->assertDatabaseHas('jokes', [
        'external_id' => 42,
        'type' => 'programming',
        'setup' => 'Why do programmers prefer dark mode?',
        'punchline' => 'Because light attracts bugs.',
    ]);

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->url() === 'https://example.test/random_joke');
});

it('обновляет существующую шутку по external_id вместо создания дубликата', function () {
    Joke::factory()->create([
        'external_id' => 42,
        'type' => 'old',
        'setup' => 'old setup',
        'punchline' => 'old punch',
    ]);

    Http::fake([
        'https://example.test/random_joke' => Http::response([
            'id' => 42,
            'type' => 'new',
            'setup' => 'new setup',
            'punchline' => 'new punch',
        ]),
    ]);

    $this->artisan('app:random-joke')->assertSuccessful();

    expect(Joke::count())->toBe(1);
    $this->assertDatabaseHas('jokes', [
        'external_id' => 42,
        'type' => 'new',
        'setup' => 'new setup',
    ]);
});

it('падает с ValidationException когда внешний API возвращает пустые обязательные поля', function () {
    Http::fake([
        'https://example.test/random_joke' => Http::response([
            'id' => 1,
            'type' => '',
            'setup' => '',
            'punchline' => '',
        ]),
    ]);

    $this->artisan('app:random-joke');
})->throws(ValidationException::class);

it('пробрасывает RequestException на 5xx ответе и не сохраняет ничего', function () {
    Http::fake([
        'https://example.test/random_joke' => Http::response('boom', 500),
    ]);

    try {
        $this->artisan('app:random-joke');
    } catch (RequestException) {
        expect(Joke::count())->toBe(0);

        return;
    }

    $this->fail('Ожидался RequestException, но он не был выброшен.');
});

it('падает с RuntimeException когда внешний API возвращает не-JSON', function () {
    Http::fake([
        'https://example.test/random_joke' => Http::response('<html>not json</html>', 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    $this->artisan('app:random-joke');
})->throws(RuntimeException::class);
