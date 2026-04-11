<?php

declare(strict_types=1);

namespace App\Enum;

enum ComicStatus: string
{
    case BUYING = 'buying';
    case DOWNLOADING = 'downloading';
    case FINISHED = 'finished';
    case STOPPED = 'stopped';
    case WISHLIST = 'wishlist';

    public function getLabel(): string
    {
        return match ($this) {
            self::BUYING => 'En cours d\'achat',
            self::DOWNLOADING => 'En cours de téléchargement',
            self::FINISHED => 'Terminée',
            self::STOPPED => 'Arrêtée',
            self::WISHLIST => 'Liste de souhaits',
        };
    }
}
