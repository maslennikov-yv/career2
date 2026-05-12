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
        $password = config('stats.system_user_password');

        if (! is_string($password) || $password === '') {
            throw new \RuntimeException('STATS_SYSTEM_USER_PASSWORD is not set');
        }

        $owner = User::firstOrCreate(
            ['email' => (string) config('stats.system_user_email')],
            [
                'name' => 'System',
                'password' => $password,
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
