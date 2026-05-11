<?php

declare(strict_types=1);

namespace App\Http\Requests\Sites;

use App\Concerns\SiteValidationRules;
use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteRequest extends FormRequest
{
    use SiteValidationRules;

    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site
            && $this->user()?->can('update', $site) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->siteRules();
    }
}
