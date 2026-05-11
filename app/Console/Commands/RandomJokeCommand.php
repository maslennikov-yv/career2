<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Actions\Jokes\CreatesJoke;
use App\Contracts\Integrations\FetchesRandomJoke;
use App\Data\Jokes\CreateJokeData;
use Illuminate\Console\Command;

class RandomJokeCommand extends Command
{
    protected $signature = 'app:random-joke';

    protected $description = 'Получает случайную шутку через FetchesRandomJoke, сохраняет её в БД и печатает в консоль.';

    public function handle(FetchesRandomJoke $fetchesRandomJoke, CreatesJoke $createsJoke): int
    {
        $joke = $createsJoke(CreateJokeData::from($fetchesRandomJoke()));

        $this->line($joke->setup);
        $this->line($joke->punchline);

        return self::SUCCESS;
    }
}
