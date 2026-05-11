<?php

declare(strict_types=1);

namespace App\Enums;

enum VisitDeviceType: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';
    case Bot = 'bot';
}
