<?php

declare(strict_types=1);

namespace App\Actions\Tracking;

use App\Contracts\Actions\Tracking\RecordsVisit;
use App\Data\Tracking\RecordVisitData;
use App\Enums\VisitDeviceType;
use App\Jobs\ResolveVisitGeoJob;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Jenssegers\Agent\Agent;
use Ramsey\Uuid\Uuid;

class RecordVisit implements RecordsVisit
{
    public function __invoke(RecordVisitData $data): ?Visit
    {
        $site = Site::query()->where('public_id', $data->public_id)->first();

        if ($site === null) {
            return null;
        }

        [$deviceType, $browser, $os] = $this->parseUserAgent($data->user_agent);

        $visit = Visit::create([
            'site_id' => $site->id,
            'visitor_uid' => $data->visitor_uid ?? $this->fingerprint($site->id, $data->ip, $data->user_agent),
            'ip' => $data->ip,
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
            'page_url' => $data->page_url,
            'referrer' => $data->referrer,
            'user_agent' => $data->user_agent,
            'occurred_at' => Carbon::now(),
        ]);

        ResolveVisitGeoJob::dispatch($visit->id);

        return $visit;
    }

    /**
     * @return array{0: VisitDeviceType, 1: ?string, 2: ?string}
     */
    private function parseUserAgent(?string $userAgent): array
    {
        if ($userAgent === null || $userAgent === '') {
            return [VisitDeviceType::Desktop, null, null];
        }

        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        $device = match (true) {
            $agent->isRobot() => VisitDeviceType::Bot,
            $agent->isTablet() => VisitDeviceType::Tablet,
            $agent->isMobile() => VisitDeviceType::Mobile,
            default => VisitDeviceType::Desktop,
        };

        return [
            $device,
            $agent->browser() ?: null,
            $agent->platform() ?: null,
        ];
    }

    private function fingerprint(int $siteId, string $ip, ?string $userAgent): string
    {
        return (string) Uuid::uuid5(Uuid::NAMESPACE_URL, "site-{$siteId}|{$ip}|{$userAgent}");
    }
}
