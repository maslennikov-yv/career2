<?php

declare(strict_types=1);

use App\Actions\Stats\GetSelfCounters;
use App\Data\Stats\GetSelfCountersData;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Cache::flush());

it('возвращает нули, если сайта с таким public_id нет', function () {
    $action = new GetSelfCounters;

    $result = $action(new GetSelfCountersData(public_id: 'nonexistent00000'));

    expect($result)->toBe([
        'public_id' => 'nonexistent00000',
        'visits' => 0,
        'uniques' => 0,
    ]);
});

it('считает визиты и уникальных посетителей', function () {
    $site = Site::factory()->create(['public_id' => 'selfsite00000001']);

    Visit::factory()->count(3)->create(['site_id' => $site->id, 'visitor_uid' => '11111111-1111-1111-1111-111111111111']);
    Visit::factory()->count(2)->create(['site_id' => $site->id, 'visitor_uid' => '22222222-2222-2222-2222-222222222222']);

    $action = new GetSelfCounters;
    $result = $action(new GetSelfCountersData(public_id: 'selfsite00000001'));

    expect($result['public_id'])->toBe('selfsite00000001')
        ->and($result['visits'])->toBe(5)
        ->and($result['uniques'])->toBe(2);
});

it('игнорирует визиты других сайтов в обоих счётчиках', function () {
    $self = Site::factory()->create(['public_id' => 'selfsite00000001']);
    $other = Site::factory()->create(['public_id' => 'othersite0000001']);

    Visit::factory()->count(2)->create([
        'site_id' => $self->id,
        'visitor_uid' => '11111111-1111-1111-1111-111111111111',
    ]);
    Visit::factory()->count(5)->create([
        'site_id' => $other->id,
        'visitor_uid' => '22222222-2222-2222-2222-222222222222',
    ]);

    $action = new GetSelfCounters;
    $result = $action(new GetSelfCountersData(public_id: 'selfsite00000001'));

    expect($result['visits'])->toBe(2)
        ->and($result['uniques'])->toBe(1);
});

it('не учитывает бот-визиты ни в visits, ни в uniques', function () {
    $site = Site::factory()->create(['public_id' => 'selfsite00000001']);

    Visit::factory()->count(2)->create([
        'site_id' => $site->id,
        'visitor_uid' => '11111111-1111-1111-1111-111111111111',
    ]);
    Visit::factory()->bot()->count(5)->create([
        'site_id' => $site->id,
        'visitor_uid' => '99999999-9999-9999-9999-999999999999',
    ]);

    $action = new GetSelfCounters;
    $result = $action(new GetSelfCountersData(public_id: 'selfsite00000001'));

    expect($result['visits'])->toBe(2)
        ->and($result['uniques'])->toBe(1);
});

it('кэширует результат и не пересчитывает в течение TTL', function () {
    $site = Site::factory()->create(['public_id' => 'cachesite0000001']);
    Visit::factory()->create(['site_id' => $site->id]);

    $action = new GetSelfCounters;

    expect($action(new GetSelfCountersData(public_id: 'cachesite0000001'))['visits'])->toBe(1);

    Visit::factory()->count(4)->create(['site_id' => $site->id]);

    expect($action(new GetSelfCountersData(public_id: 'cachesite0000001'))['visits'])->toBe(1);
});

it('GetSelfCountersData отвергает public_id неверного формата', function (string $publicId) {
    expect(fn () => GetSelfCountersData::from(['public_id' => $publicId]))
        ->toThrow(ValidationException::class);
})->with([
    'слишком короткий' => ['short'],
    'слишком длинный' => ['selfsite00000001x'],
    'верхний регистр' => ['SELFSITE00000001'],
    'дефис' => ['selfsite-0000001'],
    'пустая строка' => [''],
]);

it('GetSelfCountersData принимает 16-символьный lowercase alnum', function () {
    $data = GetSelfCountersData::from(['public_id' => 'selfsite00000001']);

    expect($data->public_id)->toBe('selfsite00000001');
});

it('делает ровно 2 запроса при cache miss и 0 при cache hit', function () {
    $site = Site::factory()->create(['public_id' => 'cachesite0000003']);
    Visit::factory()->create(['site_id' => $site->id]);

    $action = new GetSelfCounters;

    DB::enableQueryLog();

    $action(new GetSelfCountersData(public_id: 'cachesite0000003'));
    $missQueries = count(DB::getQueryLog());

    DB::flushQueryLog();

    $action(new GetSelfCountersData(public_id: 'cachesite0000003'));
    $hitQueries = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($missQueries)->toBe(2)
        ->and($hitQueries)->toBe(0);
});

it('инвалидирует кэш после Cache::flush', function () {
    $site = Site::factory()->create(['public_id' => 'cachesite0000002']);
    Visit::factory()->create(['site_id' => $site->id]);

    $action = new GetSelfCounters;

    expect($action(new GetSelfCountersData(public_id: 'cachesite0000002'))['visits'])->toBe(1);

    Visit::factory()->count(2)->create(['site_id' => $site->id]);
    Cache::flush();

    expect($action(new GetSelfCountersData(public_id: 'cachesite0000002'))['visits'])->toBe(3);
});
