<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Actions\Sites\CreatesSite;
use App\Contracts\Actions\Sites\DeletesSite;
use App\Contracts\Actions\Sites\IndexesSites;
use App\Contracts\Actions\Sites\UpdatesSite;
use App\Data\Sites\CreateSiteData;
use App\Data\Sites\DeleteSiteData;
use App\Data\Sites\IndexSitesData;
use App\Data\Sites\UpdateSiteData;
use App\Http\Requests\Sites\StoreSiteRequest;
use App\Http\Requests\Sites\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request, IndexesSites $list): Response
    {
        $sites = $list(new IndexSitesData(user_id: $request->user()->id));

        return Inertia::render('sites/Index', [
            'sites' => SiteResource::collection($sites)->resolve(),
        ]);
    }

    public function store(StoreSiteRequest $request, CreatesSite $create): RedirectResponse
    {
        $create(new CreateSiteData(
            user_id: $request->user()->id,
            name: $request->validated('name'),
            domain: $request->validated('domain'),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Сайт создан.']);

        return to_route('sites.index');
    }

    public function update(UpdateSiteRequest $request, Site $site, UpdatesSite $update): RedirectResponse
    {
        $update(new UpdateSiteData(
            id: $site->id,
            name: $request->validated('name'),
            domain: $request->validated('domain'),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Сайт обновлён.']);

        return to_route('sites.index');
    }

    public function destroy(Request $request, Site $site, DeletesSite $delete): RedirectResponse
    {
        $this->authorize('delete', $site);

        $delete(new DeleteSiteData(id: $site->id));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Сайт удалён.']);

        return to_route('sites.index');
    }
}
