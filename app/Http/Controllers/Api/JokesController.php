<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Actions\Jokes\IndexesJokes;
use App\Data\Jokes\IndexJokesData;
use App\Http\Controllers\Controller;
use App\Http\Resources\JokeResource;
use App\Models\Joke;
use Illuminate\Http\JsonResponse;

class JokesController extends Controller
{
    public function index(IndexesJokes $index): JsonResponse
    {
        return new JsonResponse(
            $index(new IndexJokesData)
                ->map(fn (Joke $joke) => (new JokeResource($joke))->resolve())
                ->all()
        );
    }
}
