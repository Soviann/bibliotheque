<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationEntityType: string
{
    case AUTHOR = 'author';
    case COMIC_SERIES = 'comic_series';
    case ENRICHMENT_PROPOSAL = 'enrichment_proposal';
}
