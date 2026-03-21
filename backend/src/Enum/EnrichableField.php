<?php

declare(strict_types=1);

namespace App\Enum;

enum EnrichableField: string
{
    case AMAZON_URL = 'amazonUrl';
    case AUTHORS = 'authors';
    case COVER = 'cover';
    case DESCRIPTION = 'description';
    case ISBN = 'isbn';
    case IS_ONE_SHOT = 'isOneShot';
    case LATEST_PUBLISHED_ISSUE = 'latestPublishedIssue';
    case PUBLISHER = 'publisher';
}
