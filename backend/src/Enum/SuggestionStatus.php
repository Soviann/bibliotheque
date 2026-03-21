<?php

declare(strict_types=1);

namespace App\Enum;

enum SuggestionStatus: string
{
    case ADDED = 'added';
    case DISMISSED = 'dismissed';
    case PENDING = 'pending';

    public function getLabel(): string
    {
        return match ($this) {
            self::ADDED => 'Ajoutée',
            self::DISMISSED => 'Ignorée',
            self::PENDING => 'En attente',
        };
    }
}
