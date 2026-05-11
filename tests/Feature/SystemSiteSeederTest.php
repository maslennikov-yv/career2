<?php

declare(strict_types=1);

use App\Models\Site;
use App\Models\User;
use Database\Seeders\SystemSiteSeeder;
use Illuminate\Support\Facades\Hash;

it('создаёт тестового пользователя test@example.com с паролем test', function () {
    $this->seed(SystemSiteSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('test', $user->password))->toBeTrue();
});

it('создаёт системный сайт с public_id из конфига и привязывает его к тестовому пользователю', function () {
    $this->seed(SystemSiteSeeder::class);

    $site = Site::query()->where('public_id', config('stats.self_site_public_id'))->first();
    $owner = User::query()->where('email', 'test@example.com')->first();

    expect($site)->not->toBeNull()
        ->and($site->user_id)->toBe($owner->id);
});

it('идемпотентен — повторный запуск не дублирует ни пользователя, ни сайт', function () {
    $this->seed(SystemSiteSeeder::class);
    $this->seed(SystemSiteSeeder::class);

    expect(User::query()->where('email', 'test@example.com')->count())->toBe(1)
        ->and(Site::query()->where('public_id', config('stats.self_site_public_id'))->count())->toBe(1);
});
