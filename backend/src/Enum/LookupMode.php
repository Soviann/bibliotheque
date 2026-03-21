<?php

declare(strict_types=1);

namespace App\Enum;

enum LookupMode: string
{
    case ISBN = 'isbn';
    case TITLE = 'title';
}
