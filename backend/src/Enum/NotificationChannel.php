<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationChannel: string
{
    case BOTH = 'both';
    case IN_APP = 'in_app';
    case OFF = 'off';
    case PUSH = 'push';

    public function getLabel(): string
    {
        return match ($this) {
            self::BOTH => 'In-app + Push',
            self::IN_APP => 'In-app uniquement',
            self::OFF => 'Désactivé',
            self::PUSH => 'Push uniquement',
        };
    }
}
