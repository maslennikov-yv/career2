<?php

declare(strict_types=1);

use App\Contracts\Actions\Tracking\RecordsVisit;
use App\Data\Tracking\RecordVisitData;
use App\Enums\VisitDeviceType;
use App\Jobs\ResolveVisitGeoJob;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Queue::fake());

it('возвращает null при неизвестном public_id и не пишет визит', function () {
    $result = app(RecordsVisit::class)(new RecordVisitData(
        public_id: 'unknown000000000',
        ip: '127.0.0.1',
        user_agent: 'curl/8.0',
    ));

    expect($result)->toBeNull();
    expect(Visit::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('создаёт визит с парсингом UA и диспатчит ResolveVisitGeoJob', function () {
    $site = Site::factory()->create();

    $visit = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '8.8.8.8',
        user_agent: 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120.0',
        page_url: 'https://example.com/page',
        referrer: 'https://google.com',
    ));

    expect($visit)
        ->toBeInstanceOf(Visit::class)
        ->site_id->toBe($site->id)
        ->ip->toBe('8.8.8.8')
        ->browser->toBe('Chrome')
        ->and($visit->device_type)->toBe(VisitDeviceType::Desktop);

    Queue::assertPushed(ResolveVisitGeoJob::class);
});

it('генерирует детерминированный visitor_uid на основе site/ip/ua', function () {
    $site = Site::factory()->create();

    $first = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '8.8.8.8',
        user_agent: 'curl/8.0',
    ));

    $second = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '8.8.8.8',
        user_agent: 'curl/8.0',
    ));

    expect($first->visitor_uid)
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i')
        ->toBe($second->visitor_uid);
});

it('даёт разные visitor_uid для разных UA при одном IP', function () {
    $site = Site::factory()->create();

    $a = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '8.8.8.8',
        user_agent: 'curl/8.0',
    ));

    $b = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '8.8.8.8',
        user_agent: 'Mozilla/5.0 Chrome/120',
    ));

    expect($a->visitor_uid)->not->toBe($b->visitor_uid);
});

it('использует переданный visitor_uid вместо fingerprint', function () {
    $site = Site::factory()->create();
    $explicit = '550e8400-e29b-41d4-a716-446655440000';

    $visit = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '8.8.8.8',
        user_agent: 'curl/8.0',
        visitor_uid: $explicit,
    ));

    expect($visit->visitor_uid)->toBe($explicit);
});

it('классифицирует Googlebot как bot', function () {
    $site = Site::factory()->create();

    $visit = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '66.249.66.1',
        user_agent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    ));

    expect($visit->device_type)->toBe(VisitDeviceType::Bot);
});

it('возвращает Desktop без browser/os при пустом UA', function () {
    $site = Site::factory()->create();

    $visit = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '8.8.8.8',
        user_agent: null,
    ));

    expect($visit->device_type)->toBe(VisitDeviceType::Desktop)
        ->and($visit->browser)->toBeNull()
        ->and($visit->os)->toBeNull();
});

it('сохраняет page_url, referrer и user_agent в визите', function () {
    $site = Site::factory()->create();

    $visit = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '8.8.8.8',
        user_agent: 'curl/8.0',
        page_url: 'https://example.com/x',
        referrer: 'https://ref.example.com',
    ));

    expect($visit->page_url)->toBe('https://example.com/x')
        ->and($visit->referrer)->toBe('https://ref.example.com')
        ->and($visit->user_agent)->toBe('curl/8.0');
});

it('ставит occurred_at на текущий момент', function () {
    $site = Site::factory()->create();
    $before = now()->subSecond();

    $visit = app(RecordsVisit::class)(new RecordVisitData(
        public_id: $site->public_id,
        ip: '8.8.8.8',
        user_agent: 'curl/8.0',
    ));

    expect($visit->occurred_at->greaterThanOrEqualTo($before))->toBeTrue();
});

it('RecordVisitData отвергает невалидный visitor_uid', function () {
    expect(fn () => RecordVisitData::from([
        'public_id' => 'aaaaaaaaaaaaaaaa',
        'ip' => '8.8.8.8',
        'user_agent' => 'curl/8.0',
        'visitor_uid' => 'not-a-uuid',
    ]))->toThrow(ValidationException::class);
});

it('RecordVisitData отвергает невалидный page_url', function () {
    expect(fn () => RecordVisitData::from([
        'public_id' => 'aaaaaaaaaaaaaaaa',
        'ip' => '8.8.8.8',
        'user_agent' => 'curl/8.0',
        'page_url' => 'javascript:alert(1)',
    ]))->toThrow(ValidationException::class);
});

it('RecordVisitData отвергает public_id длиннее 16', function () {
    expect(fn () => RecordVisitData::from([
        'public_id' => str_repeat('a', 17),
        'ip' => '8.8.8.8',
        'user_agent' => 'curl/8.0',
    ]))->toThrow(ValidationException::class);
});

it('RecordVisitData требует public_id и ip', function () {
    expect(fn () => RecordVisitData::from(['user_agent' => 'curl/8.0']))
        ->toThrow(ValidationException::class);
});
