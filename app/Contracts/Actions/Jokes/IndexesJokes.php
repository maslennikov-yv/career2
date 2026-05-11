<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Jokes;

use App\Actions\Jokes\IndexJokes;
use App\Data\Jokes\IndexJokesData;
use App\Models\Joke;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Database\Eloquent\Collection;

#[Bind(IndexJokes::class)]
interface IndexesJokes
{
    /**
     * @return Collection<int, Joke>
     */
    public function __invoke(IndexJokesData $data): Collection;
}
