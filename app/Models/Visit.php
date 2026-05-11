<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VisitDeviceType;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'visitor_uid',
    'ip',
    'country_code',
    'country',
    'region',
    'city',
    'device_type',
    'browser',
    'os',
    'page_url',
    'referrer',
    'user_agent',
    'occurred_at',
    'geo_resolved_at',
])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'geo_resolved_at' => 'datetime',
            'device_type' => VisitDeviceType::class,
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
