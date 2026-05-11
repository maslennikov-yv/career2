<?php

declare(strict_types=1);

namespace App\ModelFilters;

use EloquentFilter\ModelFilter;

class JokeFilter extends ModelFilter
{
    public function idAfter(int|string $value): self
    {
        $this->where('id', '>', (int) $value);

        return $this;
    }
}
