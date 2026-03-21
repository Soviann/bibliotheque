<?php

declare(strict_types=1);

namespace App\Enum;

enum EnrichmentAction: string
{
    case ACCEPTED = 'accepted';
    case AUTO_APPLIED = 'auto_applied';
    case REJECTED = 'rejected';
    case SKIPPED = 'skipped';
}
