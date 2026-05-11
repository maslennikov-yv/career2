<?php

declare(strict_types=1);

namespace App\Data\Tracking;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class RecordVisitData extends Data
{
    public function __construct(
        #[Required, StringType, Min(1), Max(16)]
        public string $public_id,

        #[Required, StringType, Max(45)]
        public string $ip,

        #[Nullable, StringType, Max(1024)]
        public ?string $user_agent,

        #[Nullable, Uuid]
        public ?string $visitor_uid = null,

        #[Nullable, StringType, Url, Max(2048)]
        public ?string $page_url = null,

        #[Nullable, StringType, Max(2048)]
        public ?string $referrer = null,
    ) {}
}
