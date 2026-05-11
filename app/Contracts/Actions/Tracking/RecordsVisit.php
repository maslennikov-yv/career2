<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Tracking;

use App\Actions\Tracking\RecordVisit;
use App\Data\Tracking\RecordVisitData;
use App\Models\Visit;
use Illuminate\Container\Attributes\Bind;

#[Bind(RecordVisit::class)]
interface RecordsVisit
{
    public function __invoke(RecordVisitData $data): ?Visit;
}
