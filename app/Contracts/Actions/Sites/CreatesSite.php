<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Sites;

use App\Actions\Sites\CreateSite;
use App\Data\Sites\CreateSiteData;
use App\Models\Site;
use Illuminate\Container\Attributes\Bind;

#[Bind(CreateSite::class)]
interface CreatesSite
{
    public function __invoke(CreateSiteData $data): Site;
}
