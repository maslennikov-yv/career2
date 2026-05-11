<?php

declare(strict_types=1);

namespace App\Http\Requests\Sites;

use App\Concerns\SiteValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSiteRequest extends FormRequest
{
    use SiteValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->siteRules();
    }
}
