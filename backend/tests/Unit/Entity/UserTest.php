<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Tests\Factory\EntityFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Tests unitaires pour l'entité User.
 */
final class UserTest extends TestCase
{
    // ---------------------------------------------------------------
    // Valeurs par défaut du constructeur
    // ---------------------------------------------------------------

    public function testConstructorDefaults(): void
    {
        $user = new User();

        self::assertNull($user->getId());
        self::assertNull($user->getEmail());
        self::assertNull($user->getGoogleId());
        self::assertSame(1, $user->getTokenVersion());
    }

    public function testImplementsUserInterface(): void
    {
        $user = new User();

        self::assertInstanceOf(UserInterface::class, $user);
    }

    // ---------------------------------------------------------------
    // Getters / Setters fluides
    // ---------------------------------------------------------------

    public function testSetEmailReturnsFluent(): void
    {
        $user = new User();
        $result = $user->setEmail('user@example.com');

        self::assertSame($user, $result);
        self::assertSame('user@example.com', $user->getEmail());
    }

    public function testSetGoogleIdReturnsFluent(): void
    {
        $user = new User();
        $result = $user->setGoogleId('google-123');

        self::assertSame($user, $result);
        self::assertSame('google-123', $user->getGoogleId());
    }

    public function testSetGoogleIdNull(): void
    {
        $user = new User();
        $user->setGoogleId('google-123');
        $user->setGoogleId(null);

        self::assertNull($user->getGoogleId());
    }

    public function testSetRolesReturnsFluent(): void
    {
        $user = new User();
        $result = $user->setRoles(['ROLE_ADMIN']);

        self::assertSame($user, $result);
    }

    // ---------------------------------------------------------------
    // getRoles
    // ---------------------------------------------------------------

    public function testGetRolesAlwaysContainsRoleUser(): void
    {
        $user = new User();

        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testGetRolesWithEmptyRoles(): void
    {
        $user = new User();
        $user->setRoles([]);

        $roles = $user->getRoles();

        self::assertContains('ROLE_USER', $roles);
        self::assertCount(1, $roles);
    }

    public function testGetRolesWithAdditionalRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        self::assertContains('ROLE_USER', $roles);
        self::assertContains('ROLE_ADMIN', $roles);
        self::assertCount(2, $roles);
    }

    public function testGetRolesDeduplicatesRoleUser(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_USER']);

        $roles = $user->getRoles();

        self::assertContains('ROLE_USER', $roles);
        self::assertContains('ROLE_ADMIN', $roles);
        // ROLE_USER ne doit apparaître qu'une seule fois
        self::assertCount(2, $roles);
    }

    public function testGetRolesDeduplicatesCustomRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_ADMIN']);

        $roles = $user->getRoles();

        // ROLE_ADMIN + ROLE_USER (dédupliqué)
        self::assertCount(2, $roles);
    }

    // ---------------------------------------------------------------
    // getUserIdentifier
    // ---------------------------------------------------------------

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = EntityFactory::createUser('admin@example.com');

        self::assertSame('admin@example.com', $user->getUserIdentifier());
    }

    public function testGetUserIdentifierReturnsEmptyStringWhenEmailNull(): void
    {
        $user = new User();

        self::assertSame('', $user->getUserIdentifier());
    }

    // ---------------------------------------------------------------
    // incrementTokenVersion
    // ---------------------------------------------------------------

    public function testIncrementTokenVersionReturnsFluent(): void
    {
        $user = new User();
        $result = $user->incrementTokenVersion();

        self::assertSame($user, $result);
    }

    public function testIncrementTokenVersionIncrementsBy1(): void
    {
        $user = new User();

        self::assertSame(1, $user->getTokenVersion());

        $user->incrementTokenVersion();
        self::assertSame(2, $user->getTokenVersion());

        $user->incrementTokenVersion();
        self::assertSame(3, $user->getTokenVersion());
    }

    // ---------------------------------------------------------------
    // eraseCredentials
    // ---------------------------------------------------------------

    public function testEraseCredentialsIsNoOp(): void
    {
        $user = EntityFactory::createUser('user@example.com');
        $emailBefore = $user->getEmail();
        $googleIdBefore = $user->getGoogleId();
        $rolesBefore = $user->getRoles();

        $user->eraseCredentials();

        // Aucune donnée ne doit être modifiée
        self::assertSame($emailBefore, $user->getEmail());
        self::assertSame($googleIdBefore, $user->getGoogleId());
        self::assertSame($rolesBefore, $user->getRoles());
    }

    // ---------------------------------------------------------------
    // EntityFactory
    // ---------------------------------------------------------------

    public function testFactoryCreateUserSetsValues(): void
    {
        $user = EntityFactory::createUser('custom@test.com', 'gid-42');

        self::assertSame('custom@test.com', $user->getEmail());
        self::assertSame('gid-42', $user->getGoogleId());
    }
}
