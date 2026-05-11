<?php

declare(strict_types=1);

use App\Models\Joke;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

beforeEach(function () {
    Cache::flush();
    Site::factory()->create(['public_id' => config('stats.self_site_public_id')]);
});

it('доступна гостю и рендерит компонент Welcome', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Welcome')
            ->has('jokes')
            ->has('canRegister')
            ->has('selfCounter')
        );
});

it('selfCounter содержит public_id из конфига и нули, если визитов нет', function () {
    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selfCounter.public_id', config('stats.self_site_public_id'))
            ->where('selfCounter.visits', 0)
            ->where('selfCounter.uniques', 0)
        );
});

it('selfCounter отражает реальные визиты системного сайта', function () {
    $site = Site::query()->where('public_id', config('stats.self_site_public_id'))->firstOrFail();
    Visit::factory()->count(3)->create(['site_id' => $site->id, 'visitor_uid' => '11111111-1111-1111-1111-111111111111']);
    Visit::factory()->count(1)->create(['site_id' => $site->id, 'visitor_uid' => '22222222-2222-2222-2222-222222222222']);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selfCounter.visits', 4)
            ->where('selfCounter.uniques', 2)
        );
});

it('отдаёт шутки в порядке убывания created_at', function () {
    $base = now()->subHour();
    $older = Joke::factory()->create(['created_at' => $base, 'updated_at' => $base]);
    $newer = Joke::factory()->create(['created_at' => $base->copy()->addMinutes(30), 'updated_at' => $base]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('jokes.data.0.id', $newer->id)
            ->where('jokes.data.1.id', $older->id)
            ->where('jokes.data.0.setup', $newer->setup)
        );
});

it('JokeResource не утекает external_id и updated_at', function () {
    Joke::factory()->create(['external_id' => 999]);

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('jokes.data.0', fn (AssertableInertia $joke) => $joke
                ->has('id')
                ->has('type')
                ->has('setup')
                ->has('punchline')
                ->has('created_at')
                ->missing('external_id')
                ->missing('updated_at')
            )
        );
});

it('двигается по cursor для следующей страницы', function () {
    Joke::factory()->count(25)->create();

    $first = $this->get(route('home'))->viewData('page')['props']['jokes'];

    expect($first['data'])->toHaveCount(10)
        ->and($first['next_cursor'])->not->toBeNull();

    $this->get(route('home', ['cursor' => $first['next_cursor']]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('jokes.data', 10)
            ->where('jokes.next_cursor', fn ($c) => $c !== null)
        );
});

it('latest пустой по умолчанию (optional prop) и не присутствует в payload', function () {
    Joke::factory()->count(3)->create();

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('latest')
        );
});

/**
 * @return array<string, string>
 */
function inertiaPartialHeaders(TestCase $test, string $only): array
{
    $version = $test->get(route('home'))->viewData('page')['version'] ?? '';

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'Welcome',
        'X-Inertia-Partial-Data' => $only,
    ];
}

it('latest возвращает шутки новее заданного id при partial reload с after', function () {
    $jokes = Joke::factory()->count(5)->create();
    $cutoff = $jokes[1]->id;

    $response = $this->withHeaders(inertiaPartialHeaders($this, 'latest'))
        ->get(route('home', ['after' => $cutoff]));

    $response->assertOk();
    $latest = $response->json('props.latest');

    expect($latest)
        ->toHaveCount(3)
        ->and($latest[0]['id'])->toBe($jokes[4]->id)
        ->and($latest[2]['id'])->toBe($jokes[2]->id);
});

it('latest равен пустому массиву когда after не передан в partial reload', function () {
    Joke::factory()->count(3)->create();

    $response = $this->withHeaders(inertiaPartialHeaders($this, 'latest'))
        ->get(route('home'));

    $response->assertOk();
    expect($response->json('props.latest'))->toBe([]);
});
