<?php

declare(strict_types=1);

namespace App\Contracts\Actions\Jokes;

use App\Actions\Jokes\DeleteJoke;
use App\Data\Jokes\DeleteJokeData;
use Illuminate\Container\Attributes\Bind;

#[Bind(DeleteJoke::class)]
interface DeletesJoke
{
    public function __invoke(DeleteJokeData $data): void;
}
