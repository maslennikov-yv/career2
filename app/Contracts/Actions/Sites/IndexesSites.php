<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Sites;

use App\Actions\Sites\IndexSites;
use App\Data\Sites\IndexSitesData;
use App\Models\Site;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Database\Eloquent\Collection;

#[Bind(IndexSites::class)]
interface IndexesSites
{
    /**
     * @return Collection<int, Site>
     */
    public function __invoke(IndexSitesData $data): Collection;
}
