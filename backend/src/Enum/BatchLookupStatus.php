<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statuts possibles pour le traitement batch lookup d'une série.
 */
enum BatchLookupStatus: string
{
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case UPDATED = 'updated';

    public function getLabel(): string
    {
        return match ($this) {
            self::FAILED => 'Échoué',
            self::SKIPPED => 'Ignoré',
            self::UPDATED => 'Mis à jour',
        };
    }
}
