<?php

declare(strict_types=1);

namespace App\Actions\Sites;

use App\Contracts\Actions\Sites\CreatesSite;
use App\Data\Sites\CreateSiteData;
use App\Models\Site;
use Illuminate\Support\Str;

class CreateSite implements CreatesSite
{
    public function __invoke(CreateSiteData $data): Site
    {
        return Site::create([
            'user_id' => $data->user_id,
            'name' => $data->name,
            'domain' => $data->domain,
            'public_id' => $this->generatePublicId(),
        ]);
    }

    private function generatePublicId(): string
    {
        do {
            $candidate = Str::lower(Str::random(16));
        } while (Site::where('public_id', $candidate)->exists());

        return $candidate;
    }
}
