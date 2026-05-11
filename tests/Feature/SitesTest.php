<?php

declare(strict_types=1);

use App\Models\Site;
use App\Models\User;

it('гость редиректится на login при попытке открыть список сайтов', function () {
    $this->get(route('sites.index'))->assertRedirect(route('login'));
});

it('авторизованный пользователь видит свои сайты', function () {
    $user = User::factory()->create();
    Site::factory()->for($user)->count(3)->create();
    Site::factory()->count(2)->create();

    $response = $this->actingAs($user)->get(route('sites.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('sites/Index')
            ->has('sites', 3),
    );
});

it('создаёт сайт через форму с уникальным public_id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), ['name' => 'Мой блог', 'domain' => 'example.com'])
        ->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'user_id' => $user->id,
        'name' => 'Мой блог',
        'domain' => 'example.com',
    ]);

    $site = Site::query()->firstOrFail();
    expect($site->public_id)->toHaveLength(16);
});

it('валидирует обязательное поле name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), [])
        ->assertSessionHasErrors('name');
});

it('запрещает просматривать статистику чужого сайта', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $site = Site::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('sites.stats.show', $site))
        ->assertForbidden();
});

it('запрещает удалять чужой сайт', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $site = Site::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route('sites.destroy', $site))
        ->assertForbidden();

    $this->assertDatabaseHas('sites', ['id' => $site->id]);
});

it('владелец удаляет сайт', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('sites.destroy', $site))
        ->assertRedirect(route('sites.index'));

    $this->assertDatabaseMissing('sites', ['id' => $site->id]);
});

it('обновляет сайт через форму', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create([
        'name' => 'Старое',
        'domain' => 'old.example.com',
    ]);
    $publicId = $site->public_id;

    $this->actingAs($user)
        ->patch(route('sites.update', $site), [
            'name' => 'Новое',
            'domain' => 'new.example.com',
        ])
        ->assertRedirect(route('sites.index'));

    $this->assertDatabaseHas('sites', [
        'id' => $site->id,
        'name' => 'Новое',
        'domain' => 'new.example.com',
        'public_id' => $publicId,
    ]);
});

it('запрещает обновлять чужой сайт', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $site = Site::factory()->for($owner)->create(['name' => 'Не моё']);

    $this->actingAs($intruder)
        ->patch(route('sites.update', $site), [
            'name' => 'Захвачено',
            'domain' => 'evil.example.com',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('sites', [
        'id' => $site->id,
        'name' => 'Не моё',
    ]);
});

it('валидирует обязательное name при обновлении', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('sites.update', $site), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('гость редиректится на login при попытке обновить сайт', function () {
    $site = Site::factory()->create();

    $this->patch(route('sites.update', $site), [
        'name' => 'Что-то',
        'domain' => null,
    ])->assertRedirect(route('login'));
});
