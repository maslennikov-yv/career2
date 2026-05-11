<?php

declare(strict_types=1);

use App\Contracts\Actions\Sites\IndexesSites;
use App\Data\Sites\IndexSitesData;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Validation\ValidationException;

it('возвращает только сайты указанного пользователя', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Site::factory()->for($user)->count(3)->create();
    Site::factory()->for($other)->count(2)->create();

    $sites = app(IndexesSites::class)(new IndexSitesData(user_id: $user->id));

    expect($sites)->toHaveCount(3)
        ->and($sites->pluck('user_id')->unique()->all())->toBe([$user->id]);
});

it('сортирует сайты по убыванию id (свежие первыми)', function () {
    $user = User::factory()->create();
    $first = Site::factory()->for($user)->create();
    $second = Site::factory()->for($user)->create();
    $third = Site::factory()->for($user)->create();

    $sites = app(IndexesSites::class)(new IndexSitesData(user_id: $user->id));

    expect($sites->pluck('id')->all())->toBe([$third->id, $second->id, $first->id]);
});

it('подгружает visits_count через withCount', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();
    Visit::factory()->for($site)->count(4)->create();

    $sites = app(IndexesSites::class)(new IndexSitesData(user_id: $user->id));

    expect($sites->first()->visits_count)->toBe(4);
});

it('возвращает пустую коллекцию, если у пользователя нет сайтов', function () {
    $user = User::factory()->create();

    $sites = app(IndexesSites::class)(new IndexSitesData(user_id: $user->id));

    expect($sites)->toBeEmpty();
});

it('IndexSitesData требует user_id', function () {
    expect(fn () => IndexSitesData::from([]))->toThrow(ValidationException::class);
});
