<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationEntityType;
use App\Enum\NotificationType;

/**
 * Contrat pour la création de notifications.
 */
interface NotifierInterface
{
    /**
     * Crée une notification selon les préférences de l'utilisateur.
     *
     * @param array<string, mixed>|null $metadata
     */
    public function create(
        User $user,
        NotificationType $type,
        string $title,
        string $message,
        ?NotificationEntityType $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?array $metadata = null,
    ): ?Notification;
}
