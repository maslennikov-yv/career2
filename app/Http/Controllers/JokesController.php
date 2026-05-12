<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Actions\Jokes\ListsJokes;
use App\Data\Jokes\ListJokesData;
use App\Http\Resources\JokeResource;
use App\Models\Joke;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class JokesController extends Controller
{
    public function index(Request $request, ListsJokes $list): Response
    {
        return Inertia::render('Welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            'jokes' => Inertia::scroll(fn () => $list(ListJokesData::from([
                'cursor' => $request->query('cursor'),
                'per_page' => 10,
            ]))->through(fn (Joke $joke) => (new JokeResource($joke))->resolve())),
            'latest' => Inertia::optional(function () use ($request, $list): array {
                $after = $request->integer('after');

                if ($after < 1) {
                    return [];
                }

                return collect($list(ListJokesData::from([
                    'after' => $after,
                    'per_page' => 50,
                ]))->items())
                    ->map(fn (Joke $joke) => (new JokeResource($joke))->resolve())
                    ->all();
            }),
        ]);
    }
}
