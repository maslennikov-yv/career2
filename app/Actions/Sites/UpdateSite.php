<?php

declare(strict_types=1);

namespace App\Actions\Sites;

use App\Contracts\Actions\Sites\UpdatesSite;
use App\Data\Sites\UpdateSiteData;
use App\Models\Site;

class UpdateSite implements UpdatesSite
{
    public function __invoke(UpdateSiteData $data): Site
    {
        $site = Site::query()->findOrFail($data->id);

        $site->update([
            'name' => $data->name,
            'domain' => $data->domain,
        ]);

        return $site;
    }
}
