<?php

declare(strict_types=1);

use App\Contracts\Actions\Tracking\ResolvesVisitGeo;
use App\Data\Tracking\ResolveVisitGeoData;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('заполняет страну/город из ответа ip-api', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'countryCode' => 'US',
            'country' => 'США',
            'regionName' => 'Virginia',
            'city' => 'Ашберн',
        ], 200),
    ]);

    $site = Site::factory()->create();
    $visit = Visit::factory()->for($site)->create([
        'ip' => '8.8.8.8',
        'country' => null,
        'city' => null,
        'geo_resolved_at' => null,
    ]);

    app(ResolvesVisitGeo::class)(new ResolveVisitGeoData(visit_id: $visit->id));

    $visit->refresh();
    expect($visit->country)->toBe('США')
        ->and($visit->country_code)->toBe('US')
        ->and($visit->city)->toBe('Ашберн')
        ->and($visit->region)->toBe('Virginia')
        ->and($visit->geo_resolved_at)->not->toBeNull();
});

it('пропускает приватные IP без сетевого вызова', function () {
    Http::fake();

    $site = Site::factory()->create();
    $visit = Visit::factory()->for($site)->create([
        'ip' => '192.168.0.1',
        'country' => null,
        'city' => null,
        'geo_resolved_at' => null,
    ]);

    app(ResolvesVisitGeo::class)(new ResolveVisitGeoData(visit_id: $visit->id));

    $visit->refresh();
    expect($visit->country)->toBeNull()
        ->and($visit->city)->toBeNull()
        ->and($visit->geo_resolved_at)->not->toBeNull();

    Http::assertNothingSent();
});

it('игнорирует уже обработанные посещения', function () {
    Http::fake();

    $site = Site::factory()->create();
    $visit = Visit::factory()->for($site)->create([
        'ip' => '8.8.8.8',
        'geo_resolved_at' => Carbon::now()->subMinute(),
    ]);

    app(ResolvesVisitGeo::class)(new ResolveVisitGeoData(visit_id: $visit->id));

    Http::assertNothingSent();
});

it('кэширует ответы по IP', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'countryCode' => 'US',
            'country' => 'США',
            'regionName' => 'CA',
            'city' => 'San Francisco',
        ], 200),
    ]);

    $site = Site::factory()->create();
    $first = Visit::factory()->for($site)->create(['ip' => '1.2.3.4', 'geo_resolved_at' => null, 'city' => null]);
    $second = Visit::factory()->for($site)->create(['ip' => '1.2.3.4', 'geo_resolved_at' => null, 'city' => null]);

    app(ResolvesVisitGeo::class)(new ResolveVisitGeoData(visit_id: $first->id));
    app(ResolvesVisitGeo::class)(new ResolveVisitGeoData(visit_id: $second->id));

    Http::assertSentCount(1);
});

it('не отравляет кэш пустым результатом при ошибке ip-api', function () {
    Http::fake([
        'ip-api.com/*' => Http::sequence()
            ->push(['status' => 'fail', 'message' => 'private range'], 200)
            ->push([
                'status' => 'success',
                'countryCode' => 'US',
                'country' => 'США',
                'regionName' => 'CA',
                'city' => 'San Francisco',
            ], 200),
    ]);

    $site = Site::factory()->create();
    $first = Visit::factory()->for($site)->create(['ip' => '5.6.7.8', 'geo_resolved_at' => null, 'city' => null, 'country' => null]);
    $second = Visit::factory()->for($site)->create(['ip' => '5.6.7.8', 'geo_resolved_at' => null, 'city' => null, 'country' => null]);

    app(ResolvesVisitGeo::class)(new ResolveVisitGeoData(visit_id: $first->id));
    app(ResolvesVisitGeo::class)(new ResolveVisitGeoData(visit_id: $second->id));

    expect($second->refresh()->city)->toBe('San Francisco');
    Http::assertSentCount(2);
});
