<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemSiteSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'test',
                'email_verified_at' => now(),
            ],
        );

        Site::firstOrCreate(
            ['public_id' => config('stats.self_site_public_id')],
            [
                'user_id' => $owner->id,
                'name' => 'Сам сервис',
                'domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
        );
    }
}
