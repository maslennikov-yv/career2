<?php

declare(strict_types=1);

namespace App\Actions\Jokes;

use App\Contracts\Actions\Jokes\ListsJokes;
use App\Data\Jokes\ListJokesData;
use App\Models\Joke;
use Illuminate\Contracts\Pagination\CursorPaginator;

class ListJokes implements ListsJokes
{
    public function __invoke(ListJokesData $data): CursorPaginator
    {
        return Joke::query()
            ->filter($data->filterInput())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(perPage: $data->per_page, cursor: $data->cursor);
    }
}
