<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Joke;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Joke
 */
class JokeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'setup' => $this->setup,
            'punchline' => $this->punchline,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
