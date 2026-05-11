<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Tracking;

use App\Actions\Tracking\ResolveVisitGeo;
use App\Data\Tracking\ResolveVisitGeoData;
use Illuminate\Container\Attributes\Bind;

#[Bind(ResolveVisitGeo::class)]
interface ResolvesVisitGeo
{
    public function __invoke(ResolveVisitGeoData $data): void;
}
