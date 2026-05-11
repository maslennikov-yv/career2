<?php

declare(strict_types=1);

namespace App\Actions\Sites;

use App\Contracts\Actions\Sites\DeletesSite;
use App\Data\Sites\DeleteSiteData;
use App\Models\Site;

class DeleteSite implements DeletesSite
{
    public function __invoke(DeleteSiteData $data): void
    {
        Site::findOrFail($data->id)->delete();
    }
}
