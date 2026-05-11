<?php

declare(strict_types=1);

use App\Jobs\ResolveVisitGeoJob;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Support\Facades\Queue;

it('записывает посещение и ставит задачу geo-резолва', function () {
    Queue::fake();

    $site = Site::factory()->create(['public_id' => 'aaaaaaaaaaaaaaaa']);

    $response = $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120.0',
    ])->postJson(route('api.track.store'), [
        'public_id' => $site->public_id,
        'visitor_uid' => '550e8400-e29b-41d4-a716-446655440000',
        'page_url' => 'https://example.com/page',
        'referrer' => 'https://google.com',
    ]);

    $response->assertStatus(202)->assertExactJson(['ok' => true]);

    expect(Visit::query()->count())->toBe(1);

    $visit = Visit::query()->firstOrFail();
    expect($visit->site_id)->toBe($site->id)
        ->and($visit->visitor_uid)->toBe('550e8400-e29b-41d4-a716-446655440000')
        ->and($visit->page_url)->toBe('https://example.com/page')
        ->and($visit->browser)->toBe('Chrome')
        ->and($visit->device_type->value)->toBe('desktop');

    Queue::assertPushed(ResolveVisitGeoJob::class);
});

it('возвращает 202 (без записи) при неизвестном public_id', function () {
    $response = $this->postJson(route('api.track.store'), [
        'public_id' => 'unknown000000000',
    ]);

    $response->assertStatus(202);
    expect(Visit::query()->count())->toBe(0);
});

it('возвращает 422 при отсутствии public_id', function () {
    $response = $this->postJson(route('api.track.store'), []);

    $response->assertStatus(422);
});

it('генерирует visitor_uid если клиент не передал', function () {
    $site = Site::factory()->create();

    $this->withHeaders(['User-Agent' => 'curl/8.0'])
        ->postJson(route('api.track.store'), ['public_id' => $site->public_id])
        ->assertStatus(202);

    $visit = Visit::query()->firstOrFail();
    expect($visit->visitor_uid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

it('генерирует один и тот же visitor_uid для повторного визита с тем же IP и UA', function () {
    Queue::fake();

    $site = Site::factory()->create();

    $this->withHeaders(['User-Agent' => 'curl/8.0'])
        ->postJson(route('api.track.store'), ['public_id' => $site->public_id])
        ->assertStatus(202);

    $this->withHeaders(['User-Agent' => 'curl/8.0'])
        ->postJson(route('api.track.store'), ['public_id' => $site->public_id])
        ->assertStatus(202);

    expect(Visit::query()->pluck('visitor_uid')->unique())->toHaveCount(1);
});

it('генерирует разные visitor_uid для разных UA при одном IP', function () {
    Queue::fake();

    $site = Site::factory()->create();

    $this->withHeaders(['User-Agent' => 'curl/8.0'])
        ->postJson(route('api.track.store'), ['public_id' => $site->public_id])
        ->assertStatus(202);

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
        ->postJson(route('api.track.store'), ['public_id' => $site->public_id])
        ->assertStatus(202);

    expect(Visit::query()->pluck('visitor_uid')->unique())->toHaveCount(2);
});

it('классифицирует Googlebot как bot', function () {
    Queue::fake();

    $site = Site::factory()->create();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
        ->postJson(route('api.track.store'), ['public_id' => $site->public_id])
        ->assertStatus(202);

    expect(Visit::query()->firstOrFail()->device_type->value)->toBe('bot');
});

it('отклоняет page_url с javascript-схемой', function () {
    $site = Site::factory()->create();

    $this->postJson(route('api.track.store'), [
        'public_id' => $site->public_id,
        'page_url' => 'javascript:alert(1)',
    ])->assertStatus(422)->assertJsonValidationErrors(['page_url']);
});
