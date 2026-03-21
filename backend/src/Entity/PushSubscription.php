<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\PushSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Abonnement Web Push d'un utilisateur.
 */
#[ApiResource(
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['push:list']]),
        new Post(denormalizationContext: ['groups' => ['push:write']]),
        new Delete(),
    ],
)]
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_push_endpoint', columns: ['endpoint'])]
class PushSubscription
{
    #[ORM\Column(length: 255)]
    #[Groups(['push:write'])]
    private string $authToken;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['push:list'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['push:list', 'push:write'])]
    private string $endpoint;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['push:list', 'push:write'])]
    private ?\DateTimeImmutable $expirationTime;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['push:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['push:write'])]
    private string $publicKey;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct(
        string $authToken,
        string $endpoint,
        ?\DateTimeImmutable $expirationTime,
        string $publicKey,
        User $user,
    ) {
        $this->authToken = $authToken;
        $this->createdAt = new \DateTimeImmutable();
        $this->endpoint = $endpoint;
        $this->expirationTime = $expirationTime;
        $this->publicKey = $publicKey;
        $this->user = $user;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getExpirationTime(): ?\DateTimeImmutable
    {
        return $this->expirationTime;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
