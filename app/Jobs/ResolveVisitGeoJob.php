<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Actions\Tracking\ResolvesVisitGeo;
use App\Data\Tracking\ResolveVisitGeoData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResolveVisitGeoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly int $visitId) {}

    public function handle(ResolvesVisitGeo $action): void
    {
        $action(new ResolveVisitGeoData(visit_id: $this->visitId));
    }
}
