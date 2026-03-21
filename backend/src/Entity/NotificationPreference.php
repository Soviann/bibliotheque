<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\NotificationChannel;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use App\State\NotificationPreferenceInitializer;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Préférence de notification par type et par utilisateur.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['preference:list']],
            provider: NotificationPreferenceInitializer::class,
        ),
        new Patch(denormalizationContext: ['groups' => ['preference:write']]),
    ],
    normalizationContext: ['groups' => ['preference:list']],
)]
#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_pref_user_type', columns: ['user_id', 'type'])]
class NotificationPreference
{
    #[ORM\Column(type: Types::STRING, enumType: NotificationChannel::class)]
    #[Groups(['preference:list', 'preference:write'])]
    private NotificationChannel $channel;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['preference:list'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, enumType: NotificationType::class)]
    #[Groups(['preference:list'])]
    private NotificationType $type;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct(
        NotificationChannel $channel,
        NotificationType $type,
        User $user,
    ) {
        $this->channel = $channel;
        $this->type = $type;
        $this->user = $user;
    }

    public function getChannel(): NotificationChannel
    {
        return $this->channel;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setChannel(NotificationChannel $channel): void
    {
        $this->channel = $channel;
    }
}
