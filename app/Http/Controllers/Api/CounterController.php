<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Actions\Stats\GetsSelfCounters;
use App\Data\Stats\GetSelfCountersData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CounterController extends Controller
{
    public function show(GetsSelfCounters $counters): JsonResponse
    {
        $data = $counters(new GetSelfCountersData(
            public_id: (string) config('stats.self_site_public_id'),
        ));

        return new JsonResponse($data);
    }
}
