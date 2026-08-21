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
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['push:list'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['push:list'])]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        #[Groups(['push:write'])]
        private string $authToken,
        #[ORM\Column(type: Types::TEXT)]
        #[Groups(['push:list', 'push:write'])]
        private string $endpoint,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        #[Groups(['push:list', 'push:write'])]
        private ?\DateTimeImmutable $expirationTime,
        #[ORM\Column(length: 255)]
        #[Groups(['push:write'])]
        private string $publicKey,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
    ) {
        $this->createdAt = new \DateTimeImmutable();
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
