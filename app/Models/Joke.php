<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JokeFactory;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['external_id', 'type', 'setup', 'punchline'])]
class Joke extends Model
{
    /** @use HasFactory<JokeFactory> */
    use Filterable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
        ];
    }
}
