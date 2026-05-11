<?php

declare(strict_types=1);

use App\Contracts\Actions\Sites\CreatesSite;
use App\Data\Sites\CreateSiteData;
use App\Models\Site;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('создаёт сайт со всеми полями и привязывает к пользователю', function () {
    $user = User::factory()->create();

    $site = app(CreatesSite::class)(new CreateSiteData(
        user_id: $user->id,
        name: 'Мой блог',
        domain: 'example.com',
    ));

    expect($site)
        ->toBeInstanceOf(Site::class)
        ->user_id->toBe($user->id)
        ->name->toBe('Мой блог')
        ->domain->toBe('example.com');

    $this->assertDatabaseHas('sites', [
        'id' => $site->id,
        'user_id' => $user->id,
        'name' => 'Мой блог',
        'domain' => 'example.com',
    ]);
});

it('создаёт сайт без домена', function () {
    $user = User::factory()->create();

    $site = app(CreatesSite::class)(new CreateSiteData(
        user_id: $user->id,
        name: 'Без домена',
    ));

    expect($site->domain)->toBeNull();
});

it('генерирует public_id длиной 16 в lowercase alnum', function () {
    $user = User::factory()->create();

    $site = app(CreatesSite::class)(new CreateSiteData(
        user_id: $user->id,
        name: 'Сайт',
    ));

    expect($site->public_id)
        ->toHaveLength(16)
        ->toMatch('/^[a-z0-9]{16}$/');
});

it('генерирует разные public_id для разных сайтов', function () {
    $user = User::factory()->create();

    $first = app(CreatesSite::class)(new CreateSiteData(user_id: $user->id, name: 'A'));
    $second = app(CreatesSite::class)(new CreateSiteData(user_id: $user->id, name: 'B'));

    expect($first->public_id)->not->toBe($second->public_id);
});

it('CreateSiteData отвергает пустое name', function () {
    $user = User::factory()->create();

    expect(fn () => CreateSiteData::from(['user_id' => $user->id, 'name' => '']))
        ->toThrow(ValidationException::class);
});

it('CreateSiteData отвергает name длиннее 120 символов', function () {
    $user = User::factory()->create();

    expect(fn () => CreateSiteData::from([
        'user_id' => $user->id,
        'name' => str_repeat('а', 121),
    ]))->toThrow(ValidationException::class);
});

it('CreateSiteData требует user_id', function () {
    expect(fn () => CreateSiteData::from(['name' => 'Сайт']))
        ->toThrow(ValidationException::class);
});
