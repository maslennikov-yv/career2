<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Sites;

use App\Actions\Sites\DeleteSite;
use App\Data\Sites\DeleteSiteData;
use Illuminate\Container\Attributes\Bind;

#[Bind(DeleteSite::class)]
interface DeletesSite
{
    public function __invoke(DeleteSiteData $data): void;
}
