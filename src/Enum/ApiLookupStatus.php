<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut de réponse d'une API de lookup.
 */
enum ApiLookupStatus: string
{
    case ERROR = 'error';
    case NOT_FOUND = 'not_found';
    case RATE_LIMITED = 'rate_limited';
    case SUCCESS = 'success';
}
