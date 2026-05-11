<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Actions\Stats\GetsCityBreakdown;
use App\Contracts\Actions\Stats\GetsHourlyVisits;
use App\Data\Stats\GetCityBreakdownData;
use App\Data\Stats\GetHourlyVisitsData;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatsController extends Controller
{
    public function show(Request $request, Site $site): Response
    {
        $this->authorize('view', $site);

        return Inertia::render('sites/Stats', [
            'site' => (new SiteResource($site->loadCount('visits')))->resolve(),
        ]);
    }

    public function hourly(Request $request, Site $site, GetsHourlyVisits $action): JsonResponse
    {
        $this->authorize('view', $site);

        $validated = $request->validate([
            'hours' => ['integer', 'between:1,720'],
            'timezone' => ['string', 'timezone'],
        ]);

        return new JsonResponse([
            'data' => $action(new GetHourlyVisitsData(
                site_id: $site->id,
                hours: (int) ($validated['hours'] ?? 24),
                timezone: (string) ($validated['timezone'] ?? 'UTC'),
            )),
        ]);
    }

    public function cities(Request $request, Site $site, GetsCityBreakdown $action): JsonResponse
    {
        $this->authorize('view', $site);

        $validated = $request->validate([
            'hours' => ['integer', 'between:1,720'],
            'top' => ['integer', 'between:1,50'],
        ]);

        return new JsonResponse([
            'data' => $action(new GetCityBreakdownData(
                site_id: $site->id,
                hours: (int) ($validated['hours'] ?? 24),
                top: (int) ($validated['top'] ?? 10),
            )),
        ]);
    }
}
