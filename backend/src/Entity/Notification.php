<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\NotificationEntityType;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Notification in-app pour l'utilisateur.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: true,
            paginationItemsPerPage: 20,
            order: ['createdAt' => 'DESC'],
            normalizationContext: ['groups' => ['notification:list']],
        ),
        new Get(normalizationContext: ['groups' => ['notification:read']]),
        new Patch(denormalizationContext: ['groups' => ['notification:write']]),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['notification:read']],
)]
#[ApiFilter(BooleanFilter::class, properties: ['read'])]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact'])]
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(name: 'idx_notification_user_read', columns: ['user_id', 'read_status', 'created_at'])]
class Notification
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['notification:list', 'notification:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:list', 'notification:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['notification:list', 'notification:read'])]
    private string $message;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['notification:list', 'notification:read'])]
    private ?array $metadata;

    #[ORM\Column(name: 'read_status', type: Types::BOOLEAN)]
    #[Groups(['notification:list', 'notification:read', 'notification:write'])]
    private bool $read;

    #[ORM\Column(nullable: true)]
    #[Groups(['notification:list', 'notification:read'])]
    private ?int $relatedEntityId;

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: NotificationEntityType::class)]
    #[Groups(['notification:list', 'notification:read'])]
    private ?NotificationEntityType $relatedEntityType;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:list', 'notification:read'])]
    private string $title;

    #[ORM\Column(type: Types::STRING, enumType: NotificationType::class)]
    #[Groups(['notification:list', 'notification:read'])]
    private NotificationType $type;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct(
        string $message,
        ?array $metadata,
        ?int $relatedEntityId,
        ?NotificationEntityType $relatedEntityType,
        string $title,
        NotificationType $type,
        User $user,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->message = $message;
        $this->metadata = $metadata;
        $this->read = false;
        $this->relatedEntityId = $relatedEntityId;
        $this->relatedEntityType = $relatedEntityType;
        $this->title = $title;
        $this->type = $type;
        $this->user = $user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getRelatedEntityId(): ?int
    {
        return $this->relatedEntityId;
    }

    public function getRelatedEntityType(): ?NotificationEntityType
    {
        return $this->relatedEntityType;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function markAsRead(): void
    {
        $this->read = true;
    }
}
