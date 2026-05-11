<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Jokes;

use App\Actions\Jokes\ListJokes;
use App\Data\Jokes\ListJokesData;
use App\Models\Joke;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Contracts\Pagination\CursorPaginator;

#[Bind(ListJokes::class)]
interface ListsJokes
{
    /**
     * @return CursorPaginator<int, Joke>
     */
    public function __invoke(ListJokesData $data): CursorPaginator;
}
