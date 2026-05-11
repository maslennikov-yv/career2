<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Contracts\Integrations\FetchesRandomJoke;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class RandomJokeIntegration implements FetchesRandomJoke
{
    public function __construct(protected Factory $http) {}

    /**
     * @return array{id?: int, type?: string, setup?: string, punchline?: string}
     */
    public function __invoke(): array
    {
        $endpoint = (string) Config::get('services.random_joke.endpoint');
        $timeout = (int) Config::get('services.random_joke.timeout');

        if ($endpoint === '') {
            throw new RuntimeException('services.random_joke.endpoint не сконфигурирован.');
        }

        $response = $this->http
            ->timeout($timeout)
            ->acceptJson()
            ->get($endpoint)
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('RandomJoke API вернул пустой или невалидный JSON.');
        }

        return $payload;
    }
}
