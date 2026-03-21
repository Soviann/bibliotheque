<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Notification;
use App\Entity\NotificationPreference;
use App\Entity\User;
use App\Enum\NotificationChannel;
use App\Enum\NotificationEntityType;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use App\Service\NotificationService;
use App\Service\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests unitaires pour le service de notifications.
 */
final class NotificationServiceTest extends TestCase
{
    private Stub&EntityManagerInterface $entityManager;
    private NotificationService $service;
    private Stub&NotificationPreferenceRepository $prefRepository;
    private Stub&WebPushService $webPushService;

    /** @var list<object> */
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->prefRepository = $this->createStub(NotificationPreferenceRepository::class);
        $this->webPushService = $this->createStub(WebPushService::class);

        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $this->service = new NotificationService(
            $this->entityManager,
            new NullLogger(),
            $this->prefRepository,
            $this->webPushService,
        );
    }

    public function testCreateWithInAppCreatesNotification(): void
    {
        $user = $this->createUser();
        $this->setPref($user, NotificationChannel::IN_APP);

        $result = $this->service->create($user, NotificationType::NEW_RELEASE, 'Titre', 'Message');

        self::assertInstanceOf(Notification::class, $result);
        self::assertCount(1, $this->persisted);
    }

    public function testCreateWithOffReturnsNull(): void
    {
        $user = $this->createUser();
        $this->setPref($user, NotificationChannel::OFF);

        $result = $this->service->create($user, NotificationType::NEW_RELEASE, 'Titre', 'Message');

        self::assertNull($result);
        self::assertCount(0, $this->persisted);
    }

    public function testCreateWithBothCreatesNotificationAndSendsPush(): void
    {
        $user = $this->createUser();
        $this->setPref($user, NotificationChannel::BOTH);

        $result = $this->service->create(
            $user,
            NotificationType::NEW_RELEASE,
            'Titre',
            'Message',
            NotificationEntityType::COMIC_SERIES,
            42,
        );

        self::assertInstanceOf(Notification::class, $result);
        self::assertCount(1, $this->persisted);
    }

    public function testCreateWithPushDoesNotCreateNotification(): void
    {
        $user = $this->createUser();
        $this->setPref($user, NotificationChannel::PUSH);

        $result = $this->service->create($user, NotificationType::NEW_RELEASE, 'Titre', 'Message');

        self::assertNull($result);
        self::assertCount(0, $this->persisted);
    }

    public function testCreateWithoutPrefDefaultsToInApp(): void
    {
        $user = $this->createUser();
        $this->prefRepository->method('findByUserAndType')->willReturn(null);

        $result = $this->service->create($user, NotificationType::NEW_RELEASE, 'Titre', 'Message');

        self::assertInstanceOf(Notification::class, $result);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');

        return $user;
    }

    private function setPref(User $user, NotificationChannel $channel): void
    {
        $pref = new NotificationPreference(
            channel: $channel,
            type: NotificationType::NEW_RELEASE,
            user: $user,
        );
        $this->prefRepository->method('findByUserAndType')->willReturn($pref);
    }
}
