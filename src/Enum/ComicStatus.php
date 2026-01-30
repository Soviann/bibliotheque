<?php

namespace App\Enum;

enum ComicStatus: string
{
    case BUYING = 'buying';
    case FINISHED = 'finished';
    case STOPPED = 'stopped';
    case WISHLIST = 'wishlist';

    public function getLabel(): string
    {
        return match ($this) {
            self::BUYING => 'En cours d\'achat',
            self::FINISHED => 'Terminée',
            self::STOPPED => 'Arrêtée',
            self::WISHLIST => 'Liste de souhaits',
        };
    }
}
