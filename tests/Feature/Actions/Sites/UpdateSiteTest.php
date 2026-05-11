<?php

declare(strict_types=1);

use App\Contracts\Actions\Sites\UpdatesSite;
use App\Data\Sites\UpdateSiteData;
use App\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

it('обновляет name и domain существующего сайта', function () {
    $site = Site::factory()->create([
        'name' => 'Старое',
        'domain' => 'old.example.com',
    ]);

    $updated = app(UpdatesSite::class)(new UpdateSiteData(
        id: $site->id,
        name: 'Новое',
        domain: 'new.example.com',
    ));

    expect($updated->name)->toBe('Новое')
        ->and($updated->domain)->toBe('new.example.com');

    $this->assertDatabaseHas('sites', [
        'id' => $site->id,
        'name' => 'Новое',
        'domain' => 'new.example.com',
    ]);
});

it('не меняет public_id при обновлении', function () {
    $site = Site::factory()->create();
    $originalPublicId = $site->public_id;

    app(UpdatesSite::class)(new UpdateSiteData(
        id: $site->id,
        name: 'Другое имя',
        domain: null,
    ));

    expect($site->fresh()->public_id)->toBe($originalPublicId);
});

it('зануляет domain при передаче null', function () {
    $site = Site::factory()->create(['domain' => 'было.example.com']);

    $updated = app(UpdatesSite::class)(new UpdateSiteData(
        id: $site->id,
        name: $site->name,
        domain: null,
    ));

    expect($updated->domain)->toBeNull();
});

it('бросает ModelNotFoundException, если сайта нет', function () {
    expect(fn () => app(UpdatesSite::class)(new UpdateSiteData(
        id: 99999,
        name: 'Имя',
        domain: null,
    )))->toThrow(ModelNotFoundException::class);
});

it('UpdateSiteData отвергает пустое name', function () {
    expect(fn () => UpdateSiteData::from(['id' => 1, 'name' => '']))
        ->toThrow(ValidationException::class);
});

it('UpdateSiteData отвергает name длиннее 120 символов', function () {
    expect(fn () => UpdateSiteData::from(['id' => 1, 'name' => str_repeat('а', 121)]))
        ->toThrow(ValidationException::class);
});
