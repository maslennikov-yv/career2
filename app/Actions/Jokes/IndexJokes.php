<?php

declare(strict_types=1);

namespace App\Actions\Jokes;

use App\Contracts\Actions\Jokes\IndexesJokes;
use App\Data\Jokes\IndexJokesData;
use App\Models\Joke;
use Illuminate\Database\Eloquent\Collection;

class IndexJokes implements IndexesJokes
{
    public function __invoke(IndexJokesData $data): Collection
    {
        return Joke::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
