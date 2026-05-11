<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait SiteValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function siteRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'domain' => ['nullable', 'string', 'max:255'],
        ];
    }
}
