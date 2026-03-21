<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case AUTHOR_NEW_SERIES = 'author_new_series';
    case ENRICHMENT_APPLIED = 'enrichment_applied';
    case ENRICHMENT_REVIEW = 'enrichment_review';
    case MISSING_TOME = 'missing_tome';
    case NEW_RELEASE = 'new_release';

    public function getLabel(): string
    {
        return match ($this) {
            self::AUTHOR_NEW_SERIES => 'Nouvelle série d\'un auteur suivi',
            self::ENRICHMENT_APPLIED => 'Enrichissement auto-appliqué',
            self::ENRICHMENT_REVIEW => 'Enrichissement à valider',
            self::MISSING_TOME => 'Tome manquant détecté',
            self::NEW_RELEASE => 'Nouvelle parution',
        };
    }
}
