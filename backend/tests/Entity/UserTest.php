<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité User.
 */
class UserTest extends TestCase
{
    /**
     * Teste que la classe User a l'attribut UniqueEntity sur l'email.
     */
    public function testUserHasUniqueEntityConstraintOnEmail(): void
    {
        $reflectionClass = new \ReflectionClass(User::class);
        $attributes = $reflectionClass->getAttributes();

        $hasUniqueEntity = false;
        foreach ($attributes as $attribute) {
            if (\str_contains($attribute->getName(), 'UniqueEntity')) {
                $hasUniqueEntity = true;
                $arguments = $attribute->getArguments();
                // Vérifie que c'est sur le champ email
                self::assertContains('email', $arguments);
                break;
            }
        }

        self::assertTrue($hasUniqueEntity, 'User devrait avoir une contrainte UniqueEntity sur email');
    }

    /**
     * Teste que ROLE_USER est toujours présent dans les rôles.
     */
    public function testGetRolesAlwaysContainsRoleUser(): void
    {
        $user = new User();

        $roles = $user->getRoles();

        self::assertContains('ROLE_USER', $roles);
    }

    /**
     * Teste que les rôles personnalisés sont conservés.
     */
    public function testGetRolesIncludesCustomRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        self::assertContains('ROLE_ADMIN', $roles);
        self::assertContains('ROLE_USER', $roles);
    }

    /**
     * Teste que les rôles sont dédupliqués.
     */
    public function testGetRolesDeduplicatesRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_USER']);

        $roles = $user->getRoles();

        // Compte le nombre d'occurrences de ROLE_USER
        $roleUserCount = \array_count_values($roles)['ROLE_USER'] ?? 0;
        self::assertSame(1, $roleUserCount);
    }

    /**
     * Teste que getUserIdentifier retourne l'email.
     */
    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        self::assertSame('test@example.com', $user->getUserIdentifier());
    }

    /**
     * Teste que getUserIdentifier retourne une chaîne vide si email est null.
     */
    public function testGetUserIdentifierReturnsEmptyStringWhenEmailIsNull(): void
    {
        $user = new User();

        self::assertSame('', $user->getUserIdentifier());
    }

    /**
     * Teste le getter et setter de l'email.
     */
    public function testEmailGetterAndSetter(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        self::assertSame('user@example.com', $user->getEmail());
    }

    /**
     * Teste le getter et setter du mot de passe.
     */
    public function testPasswordGetterAndSetter(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');

        self::assertSame('hashed_password', $user->getPassword());
    }

    /**
     * Teste que getId retourne null pour une nouvelle entité.
     */
    public function testGetIdReturnsNullForNewEntity(): void
    {
        $user = new User();

        self::assertNull($user->getId());
    }

    /**
     * Teste que eraseCredentials ne lève pas d'exception.
     */
    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();

        // Ne doit pas lever d'exception
        $user->eraseCredentials();

        self::assertTrue(true);
    }

    /**
     * Teste que setRoles retourne l'instance pour le chaînage.
     */
    public function testSetRolesReturnsInstance(): void
    {
        $user = new User();

        $result = $user->setRoles(['ROLE_ADMIN']);

        self::assertSame($user, $result);
    }

    /**
     * Teste que setEmail retourne l'instance pour le chaînage.
     */
    public function testSetEmailReturnsInstance(): void
    {
        $user = new User();

        $result = $user->setEmail('test@example.com');

        self::assertSame($user, $result);
    }

    /**
     * Teste que setPassword retourne l'instance pour le chaînage.
     */
    public function testSetPasswordReturnsInstance(): void
    {
        $user = new User();

        $result = $user->setPassword('password');

        self::assertSame($user, $result);
    }

    /**
     * Teste que tokenVersion est initialisé à 1.
     */
    public function testTokenVersionDefaultsToOne(): void
    {
        $user = new User();

        self::assertSame(1, $user->getTokenVersion());
    }

    /**
     * Teste l'incrémentation du tokenVersion.
     */
    public function testIncrementTokenVersion(): void
    {
        $user = new User();

        $user->incrementTokenVersion();

        self::assertSame(2, $user->getTokenVersion());
    }

    /**
     * Teste que incrementTokenVersion retourne l'instance pour le chaînage.
     */
    public function testIncrementTokenVersionReturnsInstance(): void
    {
        $user = new User();

        $result = $user->incrementTokenVersion();

        self::assertSame($user, $result);
    }
}
