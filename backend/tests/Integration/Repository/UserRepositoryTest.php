<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'integration pour UserRepository.
 */
final class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(UserRepository::class);
    }

    public function testFindByEmail(): void
    {
        $user = EntityFactory::createUser('alice@example.com', 'google-alice');
        $this->em->persist($user);
        $this->em->flush();

        $found = $this->repository->findOneBy(['email' => 'alice@example.com']);

        self::assertInstanceOf(User::class, $found);
        self::assertSame('alice@example.com', $found->getEmail());
        self::assertSame('google-alice', $found->getGoogleId());
    }

    public function testFindByGoogleId(): void
    {
        $user = EntityFactory::createUser('bob@example.com', 'google-bob-123');
        $this->em->persist($user);
        $this->em->flush();

        $found = $this->repository->findOneBy(['googleId' => 'google-bob-123']);

        self::assertInstanceOf(User::class, $found);
        self::assertSame('bob@example.com', $found->getEmail());
    }

    public function testUserNotFoundReturnsNull(): void
    {
        $found = $this->repository->findOneBy(['email' => 'unknown@nowhere.com']);

        self::assertNull($found);
    }
}
