<?php

declare(strict_types=1);

namespace App\Enum;

enum ProposalStatus: string
{
    case ACCEPTED = 'accepted';
    case PENDING = 'pending';
    case PRE_ACCEPTED = 'pre_accepted';
    case REJECTED = 'rejected';
    case SKIPPED = 'skipped';
}
