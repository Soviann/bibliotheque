<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\JwtTokenVersionListener;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le listener de version de token JWT.
 */
class JwtTokenVersionListenerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private JwtTokenVersionListener $listener;
    private UserRepository&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->listener = new JwtTokenVersionListener($this->entityManager, $this->userRepository);
    }

    /**
     * Teste que onJWTCreated incrémente et ajoute la tokenVersion au payload.
     */
    public function testOnJwtCreatedIncrementsAndAddsTokenVersion(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        self::assertSame(1, $user->getTokenVersion());

        $this->entityManager->expects(self::once())->method('flush');

        $event = new JWTCreatedEvent(['username' => 'test@example.com'], $user, []);

        $this->listener->onJWTCreated($event);

        $data = $event->getData();
        self::assertArrayHasKey('tokenVersion', $data);
        self::assertSame(2, $data['tokenVersion']);
        self::assertSame(2, $user->getTokenVersion());
    }

    /**
     * Teste que chaque appel à onJWTCreated incrémente la version.
     */
    public function testOnJwtCreatedIncrementsEachTime(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->entityManager->expects(self::exactly(2))->method('flush');

        $event1 = new JWTCreatedEvent(['username' => 'test@example.com'], $user, []);
        $this->listener->onJWTCreated($event1);
        self::assertSame(2, $event1->getData()['tokenVersion']);

        $event2 = new JWTCreatedEvent(['username' => 'test@example.com'], $user, []);
        $this->listener->onJWTCreated($event2);
        self::assertSame(3, $event2->getData()['tokenVersion']);
    }

    /**
     * Teste que onJWTDecoded invalide un token dont la version ne correspond pas.
     */
    public function testOnJwtDecodedInvalidatesOutdatedToken(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->incrementTokenVersion(); // version = 2

        $this->userRepository->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $event = new JWTDecodedEvent([
            'username' => 'test@example.com',
            'tokenVersion' => 1, // version obsolète
        ]);

        $this->listener->onJWTDecoded($event);

        self::assertFalse($event->isValid());
    }

    /**
     * Teste que onJWTDecoded accepte un token avec la bonne version.
     */
    public function testOnJwtDecodedAcceptsValidToken(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->userRepository->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $event = new JWTDecodedEvent([
            'username' => 'test@example.com',
            'tokenVersion' => 1,
        ]);

        $this->listener->onJWTDecoded($event);

        self::assertTrue($event->isValid());
    }

    /**
     * Teste que onJWTDecoded invalide un token sans tokenVersion.
     */
    public function testOnJwtDecodedInvalidatesTokenWithoutVersion(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->userRepository->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $event = new JWTDecodedEvent([
            'username' => 'test@example.com',
        ]);

        $this->listener->onJWTDecoded($event);

        self::assertFalse($event->isValid());
    }

    /**
     * Teste que onJWTDecoded invalide un token si l'utilisateur n'existe plus.
     */
    public function testOnJwtDecodedInvalidatesTokenForMissingUser(): void
    {
        $this->userRepository->method('findOneBy')
            ->with(['email' => 'deleted@example.com'])
            ->willReturn(null);

        $event = new JWTDecodedEvent([
            'username' => 'deleted@example.com',
            'tokenVersion' => 1,
        ]);

        $this->listener->onJWTDecoded($event);

        self::assertFalse($event->isValid());
    }
}
