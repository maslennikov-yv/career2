<?php

declare(strict_types=1);

use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;

it('возвращает почасовую статистику с заполненными нулями', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    $now = Carbon::now()->startOfHour();
    Visit::factory()->for($site)->create([
        'visitor_uid' => '11111111-1111-1111-1111-111111111111',
        'occurred_at' => $now->copy()->subHour(),
    ]);
    Visit::factory()->for($site)->create([
        'visitor_uid' => '22222222-2222-2222-2222-222222222222',
        'occurred_at' => $now->copy()->subHour(),
    ]);
    Visit::factory()->for($site)->create([
        'visitor_uid' => '11111111-1111-1111-1111-111111111111',
        'occurred_at' => $now->copy()->subHour(),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('sites.stats.hourly', ['site' => $site->id, 'hours' => 24]));

    $response->assertOk();

    $payload = $response->json('data');
    expect($payload)->toHaveCount(24);

    $totalUniques = collect($payload)->sum('uniques');
    expect($totalUniques)->toBe(2);
});

it('возвращает топ городов и группирует остальное в "Прочее"', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    Visit::factory()->for($site)->count(5)->state(['city' => 'Москва', 'occurred_at' => Carbon::now()->subMinutes(10)])->create();
    Visit::factory()->for($site)->count(3)->state(['city' => 'Питер', 'occurred_at' => Carbon::now()->subMinutes(10)])->create();
    Visit::factory()->for($site)->count(2)->state(['city' => 'Воронеж', 'occurred_at' => Carbon::now()->subMinutes(10)])->create();

    $response = $this->actingAs($user)
        ->getJson(route('sites.stats.cities', ['site' => $site->id, 'hours' => 24, 'top' => 2]));

    $response->assertOk();

    $payload = $response->json('data');
    expect($payload)->toHaveCount(3)
        ->and($payload[0]['city'])->toBe('Москва')
        ->and($payload[0]['visits'])->toBe(5)
        ->and($payload[1]['city'])->toBe('Питер')
        ->and($payload[1]['visits'])->toBe(3)
        ->and($payload[2]['city'])->toBe('Прочее')
        ->and($payload[2]['visits'])->toBe(2);
});

it('запрещает чужому пользователю смотреть статистику', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $site = Site::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->getJson(route('sites.stats.hourly', $site))
        ->assertForbidden();
});

it('валидирует диапазон hours и top', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson(route('sites.stats.hourly', ['site' => $site->id, 'hours' => 99999]))
        ->assertStatus(422);

    $this->actingAs($user)
        ->getJson(route('sites.stats.hourly', ['site' => $site->id, 'hours' => 0]))
        ->assertStatus(422);

    $this->actingAs($user)
        ->getJson(route('sites.stats.cities', ['site' => $site->id, 'top' => 1000]))
        ->assertStatus(422);
});

it('группирует часы в TZ клиента, а не сервера', function () {
    Carbon::setTestNow('2026-05-09 12:30:00 UTC');

    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    Visit::factory()->for($site)->create([
        'visitor_uid' => '11111111-1111-1111-1111-111111111111',
        'occurred_at' => Carbon::parse('2026-05-09 11:45:00 UTC'),
    ]);

    $mskKey = collect(
        $this->actingAs($user)
            ->getJson(route('sites.stats.hourly', [
                'site' => $site->id,
                'hours' => 24,
                'timezone' => 'Europe/Moscow',
            ]))
            ->json('data'),
    )->firstWhere('uniques', 1);

    expect($mskKey['hour'])
        ->toBe('2026-05-09T14:00:00')
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00$/');

    $utcKey = collect(
        $this->actingAs($user)
            ->getJson(route('sites.stats.hourly', [
                'site' => $site->id,
                'hours' => 24,
                'timezone' => 'UTC',
            ]))
            ->json('data'),
    )->firstWhere('uniques', 1);

    expect($utcKey['hour'])
        ->toBe('2026-05-09T11:00:00')
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00$/');
});

it('возвращает все hour-ключи в формате YYYY-MM-DDTHH:MI:00 без TZ-суффикса', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    $points = $this->actingAs($user)
        ->getJson(route('sites.stats.hourly', [
            'site' => $site->id,
            'hours' => 24,
            'timezone' => 'Asia/Tokyo',
        ]))
        ->json('data');

    expect($points)->not->toBeEmpty();

    foreach ($points as $point) {
        expect($point['hour'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00$/');
    }
});

it('отклоняет невалидный timezone', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson(route('sites.stats.hourly', [
            'site' => $site->id,
            'timezone' => 'Mars/Olympus_Mons',
        ]))
        ->assertStatus(422);
});
