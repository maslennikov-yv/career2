<?php

declare(strict_types=1);

namespace App\Actions\Sites;

use App\Contracts\Actions\Sites\IndexesSites;
use App\Data\Sites\IndexSitesData;
use App\Models\Site;
use Illuminate\Database\Eloquent\Collection;

class IndexSites implements IndexesSites
{
    public function __invoke(IndexSitesData $data): Collection
    {
        return Site::query()
            ->where('user_id', $data->user_id)
            ->withCount('visits')
            ->orderByDesc('id')
            ->get();
    }
}
