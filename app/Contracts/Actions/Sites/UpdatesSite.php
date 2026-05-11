<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Sites;

use App\Actions\Sites\UpdateSite;
use App\Data\Sites\UpdateSiteData;
use App\Models\Site;
use Illuminate\Container\Attributes\Bind;

#[Bind(UpdateSite::class)]
interface UpdatesSite
{
    public function __invoke(UpdateSiteData $data): Site;
}
