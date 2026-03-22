<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'integration pour AuthorRepository.
 */
final class AuthorRepositoryTest extends KernelTestCase
{
    private AuthorRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(AuthorRepository::class);
    }

    // ---------------------------------------------------------------
    // findOrCreate
    // ---------------------------------------------------------------

    public function testFindOrCreateNewAuthorCreatesAndReturns(): void
    {
        $author = $this->repository->findOrCreate('Goscinny');
        $this->em->flush();

        self::assertInstanceOf(Author::class, $author);
        self::assertSame('Goscinny', $author->getName());
        self::assertNotNull($author->getId());
    }

    public function testFindOrCreateExistingAuthorReturnsSameInstance(): void
    {
        $existing = EntityFactory::createAuthor('Uderzo');
        $this->em->persist($existing);
        $this->em->flush();

        $existingId = $existing->getId();

        $found = $this->repository->findOrCreate('Uderzo');

        self::assertSame($existingId, $found->getId());
        self::assertSame('Uderzo', $found->getName());
    }

    public function testFindOrCreateTrimsWhitespace(): void
    {
        $author = $this->repository->findOrCreate('  Moebius  ');
        $this->em->flush();

        self::assertSame('Moebius', $author->getName());

        // Un second appel avec le nom propre retourne le meme auteur
        $same = $this->repository->findOrCreate('Moebius');

        self::assertSame($author->getId(), $same->getId());
    }

    // ---------------------------------------------------------------
    // findOrCreateMultiple
    // ---------------------------------------------------------------

    public function testFindOrCreateMultipleMixedNewAndExisting(): void
    {
        $existing = EntityFactory::createAuthor('Toriyama');
        $this->em->persist($existing);
        $this->em->flush();

        $authors = $this->repository->findOrCreateMultiple(['Toriyama', 'Oda', 'Kishimoto']);
        $this->em->flush();

        self::assertCount(3, $authors);

        $names = \array_map(static fn (Author $a): string => $a->getName(), $authors);
        self::assertContains('Toriyama', $names);
        self::assertContains('Oda', $names);
        self::assertContains('Kishimoto', $names);

        // L'auteur existant conserve son ID
        $toriyama = \array_values(\array_filter($authors, static fn (Author $a): bool => 'Toriyama' === $a->getName()));
        self::assertSame($existing->getId(), $toriyama[0]->getId());
    }

    public function testFindOrCreateMultipleSkipsEmptyNames(): void
    {
        $authors = $this->repository->findOrCreateMultiple(['Hergé', '', '  ', 'Franquin']);
        $this->em->flush();

        self::assertCount(2, $authors);

        $names = \array_map(static fn (Author $a): string => $a->getName(), $authors);
        self::assertContains('Hergé', $names);
        self::assertContains('Franquin', $names);
    }

    // ---------------------------------------------------------------
    // findFollowed
    // ---------------------------------------------------------------

    public function testFindFollowedReturnsOnlyFollowedAuthorsWithSeries(): void
    {
        $followed = EntityFactory::createAuthor('Urasawa');
        $followed->setFollowedForNewSeries(true);

        $series = EntityFactory::createComicSeries('Monster');
        $followed->addComicSeries($series);

        $notFollowed = EntityFactory::createAuthor('Oda');

        $this->em->persist($followed);
        $this->em->persist($notFollowed);
        $this->em->persist($series);
        $this->em->flush();
        $this->em->clear();

        $result = $this->repository->findFollowed();

        self::assertCount(1, $result);
        self::assertSame('Urasawa', $result[0]->getName());
        // Vérifie que les séries sont pré-chargées (pas de lazy loading)
        $collection = $result[0]->getComicSeries();
        self::assertInstanceOf(PersistentCollection::class, $collection);
        self::assertTrue($collection->isInitialized());
        self::assertCount(1, $result[0]->getComicSeries());
    }

    public function testFindFollowedReturnsEmptyWhenNoneFollowed(): void
    {
        $author = EntityFactory::createAuthor('Toriyama');
        $this->em->persist($author);
        $this->em->flush();

        $result = $this->repository->findFollowed();

        self::assertSame([], $result);
    }
}
