<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\JwtTokenVersionListener;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Tests unitaires pour JwtTokenVersionListener.
 */
final class JwtTokenVersionListenerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private JwtTokenVersionListener $listener;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->userRepository = $this->createStub(UserRepository::class);

        $this->listener = new JwtTokenVersionListener(
            $this->entityManager,
            $this->userRepository,
        );
    }

    /**
     * Teste que onJWTCreated incr\u00e9mente la version, flush et ajoute tokenVersion au payload.
     */
    public function testOnJwtCreatedWithUserIncrementsVersionAndAddsToPayload(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $initialVersion = $user->getTokenVersion();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $listener = new JwtTokenVersionListener($entityManager, $this->userRepository);

        $event = new JWTCreatedEvent(['username' => 'test@example.com'], $user);

        $listener->onJWTCreated($event);

        self::assertSame($initialVersion + 1, $user->getTokenVersion());

        $data = $event->getData();
        self::assertArrayHasKey('tokenVersion', $data);
        self::assertSame($user->getTokenVersion(), $data['tokenVersion']);
    }

    /**
     * Teste que onJWTCreated preserve les champs pre-existants du payload (username, roles).
     */
    public function testOnJwtCreatedPreservesExistingPayloadFields(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $event = new JWTCreatedEvent([
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
            'username' => 'test@example.com',
        ], $user);

        $this->listener->onJWTCreated($event);

        $data = $event->getData();

        // Les champs originaux doivent etre preserves
        self::assertSame('test@example.com', $data['username']);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $data['roles']);
        // Le champ tokenVersion doit aussi etre present
        self::assertArrayHasKey('tokenVersion', $data);
    }

    /**
     * Teste que onJWTCreated ne fait rien si l'utilisateur n'est pas une instance de User.
     */
    public function testOnJwtCreatedWithNonUserDoesNothing(): void
    {
        $user = $this->createStub(UserInterface::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::never())
            ->method('flush');

        $listener = new JwtTokenVersionListener($entityManager, $this->userRepository);

        $event = new JWTCreatedEvent(['username' => 'test@example.com'], $user);

        $listener->onJWTCreated($event);

        $data = $event->getData();
        self::assertArrayNotHasKey('tokenVersion', $data);
    }

    /**
     * Teste que onJWTDecoded ne marque pas le token invalide quand la version correspond.
     */
    public function testOnJwtDecodedWithValidPayloadAndMatchingVersion(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        // La version par d\u00e9faut est 1
        $tokenVersion = $user->getTokenVersion();

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $listener = new JwtTokenVersionListener($this->entityManager, $userRepository);

        $event = new JWTDecodedEvent([
            'tokenVersion' => $tokenVersion,
            'username' => 'test@example.com',
        ]);

        $listener->onJWTDecoded($event);

        self::assertTrue($event->isValid());
    }

    /**
     * Teste que onJWTDecoded marque invalide quand tokenVersion est absent du payload.
     */
    public function testOnJwtDecodedWithMissingTokenVersionMarksInvalid(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::never())
            ->method('findOneBy');

        $listener = new JwtTokenVersionListener($this->entityManager, $userRepository);

        $event = new JWTDecodedEvent([
            'username' => 'test@example.com',
        ]);

        $listener->onJWTDecoded($event);

        self::assertFalse($event->isValid());
    }

    /**
     * Teste que onJWTDecoded marque invalide quand username est absent du payload.
     */
    public function testOnJwtDecodedWithMissingUsernameMarksInvalid(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::never())
            ->method('findOneBy');

        $listener = new JwtTokenVersionListener($this->entityManager, $userRepository);

        $event = new JWTDecodedEvent([
            'tokenVersion' => 1,
        ]);

        $listener->onJWTDecoded($event);

        self::assertFalse($event->isValid());
    }

    /**
     * Teste que onJWTDecoded marque invalide quand l'utilisateur n'est pas trouv\u00e9.
     */
    public function testOnJwtDecodedWithUserNotFoundMarksInvalid(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'unknown@example.com'])
            ->willReturn(null);

        $listener = new JwtTokenVersionListener($this->entityManager, $userRepository);

        $event = new JWTDecodedEvent([
            'tokenVersion' => 1,
            'username' => 'unknown@example.com',
        ]);

        $listener->onJWTDecoded($event);

        self::assertFalse($event->isValid());
    }

    /**
     * Teste que onJWTDecoded marque invalide quand la version ne correspond pas.
     */
    public function testOnJwtDecodedWithVersionMismatchMarksInvalid(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $listener = new JwtTokenVersionListener($this->entityManager, $userRepository);

        // Version de l'utilisateur = 1, version dans le token = 99
        $event = new JWTDecodedEvent([
            'tokenVersion' => 99,
            'username' => 'test@example.com',
        ]);

        $listener->onJWTDecoded($event);

        self::assertFalse($event->isValid());
    }
}
