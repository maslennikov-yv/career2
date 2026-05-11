<?php

declare(strict_types=1);

use App\Contracts\Actions\Stats\GetsHourlyVisits;
use App\Data\Stats\GetHourlyVisitsData;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('возвращает массив длиной hours с заполненными нулями', function () {
    $site = Site::factory()->create();

    $points = app(GetsHourlyVisits::class)(new GetHourlyVisitsData(site_id: $site->id, hours: 24));

    expect($points)->toHaveCount(24)
        ->and(collect($points)->sum('uniques'))->toBe(0);
});

it('считает уникальных visitor_uid в часовом окне', function () {
    $site = Site::factory()->create();
    $hour = Carbon::now()->subHour();

    Visit::factory()->for($site)->create([
        'visitor_uid' => '11111111-1111-1111-1111-111111111111',
        'occurred_at' => $hour,
    ]);
    Visit::factory()->for($site)->create([
        'visitor_uid' => '11111111-1111-1111-1111-111111111111',
        'occurred_at' => $hour,
    ]);
    Visit::factory()->for($site)->create([
        'visitor_uid' => '22222222-2222-2222-2222-222222222222',
        'occurred_at' => $hour,
    ]);

    $points = app(GetsHourlyVisits::class)(new GetHourlyVisitsData(site_id: $site->id, hours: 24));

    expect(collect($points)->sum('uniques'))->toBe(2);
});

it('игнорирует визиты других сайтов', function () {
    $site = Site::factory()->create();
    $other = Site::factory()->create();

    Visit::factory()->for($other)->count(5)->create([
        'visitor_uid' => '33333333-3333-3333-3333-333333333333',
        'occurred_at' => Carbon::now()->subMinutes(10),
    ]);

    $points = app(GetsHourlyVisits::class)(new GetHourlyVisitsData(site_id: $site->id, hours: 24));

    expect(collect($points)->sum('uniques'))->toBe(0);
});

it('игнорирует визиты вне окна hours', function () {
    $site = Site::factory()->create();

    Visit::factory()->for($site)->create([
        'visitor_uid' => '11111111-1111-1111-1111-111111111111',
        'occurred_at' => Carbon::now()->subDays(10),
    ]);

    $points = app(GetsHourlyVisits::class)(new GetHourlyVisitsData(site_id: $site->id, hours: 24));

    expect(collect($points)->sum('uniques'))->toBe(0);
});

it('возвращает все hour-ключи в формате YYYY-MM-DDTHH:MI:00 без TZ-суффикса', function () {
    $site = Site::factory()->create();

    $points = app(GetsHourlyVisits::class)(new GetHourlyVisitsData(
        site_id: $site->id,
        hours: 6,
        timezone: 'Asia/Tokyo',
    ));

    foreach ($points as $point) {
        expect($point['hour'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00$/');
    }
});

it('группирует часы в TZ клиента, а не сервера', function () {
    Carbon::setTestNow('2026-05-09 12:30:00 UTC');

    $site = Site::factory()->create();

    Visit::factory()->for($site)->create([
        'visitor_uid' => '11111111-1111-1111-1111-111111111111',
        'occurred_at' => Carbon::parse('2026-05-09 11:45:00 UTC'),
    ]);

    $msk = collect(app(GetsHourlyVisits::class)(new GetHourlyVisitsData(
        site_id: $site->id,
        hours: 24,
        timezone: 'Europe/Moscow',
    )))->firstWhere('uniques', 1);

    $utc = collect(app(GetsHourlyVisits::class)(new GetHourlyVisitsData(
        site_id: $site->id,
        hours: 24,
        timezone: 'UTC',
    )))->firstWhere('uniques', 1);

    expect($msk['hour'])->toBe('2026-05-09T14:00:00')
        ->and($utc['hour'])->toBe('2026-05-09T11:00:00');
});

it('GetHourlyVisitsData отвергает hours вне диапазона', function (int $invalid) {
    expect(fn () => GetHourlyVisitsData::from(['site_id' => 1, 'hours' => $invalid]))
        ->toThrow(ValidationException::class);
})->with([0, 721, 99999]);

it('GetHourlyVisitsData отвергает невалидный timezone', function () {
    expect(fn () => GetHourlyVisitsData::from([
        'site_id' => 1,
        'timezone' => 'Mars/Olympus_Mons',
    ]))->toThrow(ValidationException::class);
});

it('GetHourlyVisitsData требует site_id', function () {
    expect(fn () => GetHourlyVisitsData::from([]))->toThrow(ValidationException::class);
});
