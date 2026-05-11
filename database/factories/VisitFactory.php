<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VisitDeviceType;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $occurredAt = fake()->dateTimeBetween('-7 days', 'now');

        return [
            'site_id' => Site::factory(),
            'visitor_uid' => (string) Str::uuid(),
            'ip' => fake()->ipv4(),
            'country_code' => fake()->countryCode(),
            'country' => fake()->country(),
            'region' => fake()->state(),
            'city' => fake()->city(),
            'device_type' => fake()->randomElement([
                VisitDeviceType::Desktop,
                VisitDeviceType::Mobile,
                VisitDeviceType::Tablet,
            ]),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'os' => fake()->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
            'page_url' => fake()->url(),
            'referrer' => fake()->optional()->url(),
            'user_agent' => fake()->userAgent(),
            'occurred_at' => $occurredAt,
            'geo_resolved_at' => $occurredAt,
        ];
    }

    public function bot(): static
    {
        return $this->state(fn () => ['device_type' => VisitDeviceType::Bot]);
    }
}
