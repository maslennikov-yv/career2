<?php

declare(strict_types=1);

use App\Contracts\Actions\Stats\GetsCityBreakdown;
use App\Data\Stats\GetCityBreakdownData;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('возвращает топ городов в порядке убывания visits', function () {
    $site = Site::factory()->create();

    Visit::factory()->for($site)->count(2)->create(['city' => 'Воронеж', 'occurred_at' => Carbon::now()->subMinutes(10)]);
    Visit::factory()->for($site)->count(5)->create(['city' => 'Москва', 'occurred_at' => Carbon::now()->subMinutes(10)]);
    Visit::factory()->for($site)->count(3)->create(['city' => 'Питер', 'occurred_at' => Carbon::now()->subMinutes(10)]);

    $rows = app(GetsCityBreakdown::class)(new GetCityBreakdownData(site_id: $site->id, hours: 24, top: 10));

    expect($rows)->toBe([
        ['city' => 'Москва', 'visits' => 5],
        ['city' => 'Питер', 'visits' => 3],
        ['city' => 'Воронеж', 'visits' => 2],
    ]);
});

it('группирует остальное в "Прочее" когда городов больше top', function () {
    $site = Site::factory()->create();

    Visit::factory()->for($site)->count(5)->create(['city' => 'Москва', 'occurred_at' => Carbon::now()->subMinutes(10)]);
    Visit::factory()->for($site)->count(3)->create(['city' => 'Питер', 'occurred_at' => Carbon::now()->subMinutes(10)]);
    Visit::factory()->for($site)->count(2)->create(['city' => 'Воронеж', 'occurred_at' => Carbon::now()->subMinutes(10)]);

    $rows = app(GetsCityBreakdown::class)(new GetCityBreakdownData(site_id: $site->id, hours: 24, top: 2));

    expect($rows)->toBe([
        ['city' => 'Москва', 'visits' => 5],
        ['city' => 'Питер', 'visits' => 3],
        ['city' => 'Прочее', 'visits' => 2],
    ]);
});

it('не добавляет "Прочее", если городов ровно top', function () {
    $site = Site::factory()->create();

    Visit::factory()->for($site)->count(2)->create(['city' => 'Москва', 'occurred_at' => Carbon::now()->subMinutes(10)]);
    Visit::factory()->for($site)->count(1)->create(['city' => 'Питер', 'occurred_at' => Carbon::now()->subMinutes(10)]);

    $rows = app(GetsCityBreakdown::class)(new GetCityBreakdownData(site_id: $site->id, hours: 24, top: 2));

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('city')->all())->not->toContain('Прочее');
});

it('подменяет null city на "Неизвестно"', function () {
    $site = Site::factory()->create();

    Visit::factory()->for($site)->count(3)->create(['city' => null, 'occurred_at' => Carbon::now()->subMinutes(10)]);

    $rows = app(GetsCityBreakdown::class)(new GetCityBreakdownData(site_id: $site->id, hours: 24));

    expect($rows)->toBe([['city' => 'Неизвестно', 'visits' => 3]]);
});

it('игнорирует визиты вне окна hours', function () {
    $site = Site::factory()->create();

    Visit::factory()->for($site)->create(['city' => 'Москва', 'occurred_at' => Carbon::now()->subDays(10)]);

    $rows = app(GetsCityBreakdown::class)(new GetCityBreakdownData(site_id: $site->id, hours: 24));

    expect($rows)->toBe([]);
});

it('игнорирует визиты других сайтов', function () {
    $site = Site::factory()->create();
    $other = Site::factory()->create();

    Visit::factory()->for($other)->count(5)->create(['city' => 'Москва', 'occurred_at' => Carbon::now()->subMinutes(10)]);

    $rows = app(GetsCityBreakdown::class)(new GetCityBreakdownData(site_id: $site->id, hours: 24));

    expect($rows)->toBe([]);
});

it('возвращает пустой массив, если визитов нет', function () {
    $site = Site::factory()->create();

    $rows = app(GetsCityBreakdown::class)(new GetCityBreakdownData(site_id: $site->id, hours: 24));

    expect($rows)->toBe([]);
});

it('GetCityBreakdownData отвергает top вне диапазона', function (int $invalid) {
    expect(fn () => GetCityBreakdownData::from(['site_id' => 1, 'top' => $invalid]))
        ->toThrow(ValidationException::class);
})->with([0, 51, 1000]);

it('GetCityBreakdownData отвергает hours вне диапазона', function (int $invalid) {
    expect(fn () => GetCityBreakdownData::from(['site_id' => 1, 'hours' => $invalid]))
        ->toThrow(ValidationException::class);
})->with([0, 721]);
