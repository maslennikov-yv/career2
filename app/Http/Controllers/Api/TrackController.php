<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Actions\Tracking\RecordsVisit;
use App\Data\Tracking\RecordVisitData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    public function store(Request $request, RecordsVisit $record): JsonResponse
    {
        $validated = $request->validate([
            'public_id' => ['required', 'string', 'min:1', 'max:16'],
            'visitor_uid' => ['nullable', 'uuid'],
            'page_url' => ['nullable', 'url:http,https', 'max:2048'],
            'referrer' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $record(new RecordVisitData(
            public_id: $validated['public_id'],
            ip: $request->ip() ?? '0.0.0.0',
            user_agent: $request->userAgent(),
            visitor_uid: $validated['visitor_uid'] ?? null,
            page_url: $validated['page_url'] ?? null,
            referrer: $validated['referrer'] ?? null,
        ));

        return new JsonResponse(['ok' => true], 202);
    }
}
