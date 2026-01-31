<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration pour AuthorRepository.
 */
class AuthorRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AuthorRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(Author::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Teste findOrCreate avec un nouvel auteur.
     */
    public function testFindOrCreateWithNewAuthor(): void
    {
        $authorName = 'New Author Unique Test '.uniqid();

        $author = $this->repository->findOrCreate($authorName);

        self::assertInstanceOf(Author::class, $author);
        self::assertSame($authorName, $author->getName());

        // Nettoyer
        $this->em->remove($author);
        $this->em->flush();
    }

    /**
     * Teste findOrCreate avec un auteur existant.
     */
    public function testFindOrCreateWithExistingAuthor(): void
    {
        $authorName = 'Existing Author Test '.uniqid();

        // Créer l'auteur d'abord
        $existingAuthor = new Author();
        $existingAuthor->setName($authorName);
        $this->em->persist($existingAuthor);
        $this->em->flush();

        $existingId = $existingAuthor->getId();

        // Appeler findOrCreate avec le même nom
        $foundAuthor = $this->repository->findOrCreate($authorName);

        self::assertSame($existingId, $foundAuthor->getId());
        self::assertSame($authorName, $foundAuthor->getName());

        // Nettoyer
        $this->em->remove($foundAuthor);
        $this->em->flush();
    }

    /**
     * Teste findOrCreate avec trimming du nom.
     */
    public function testFindOrCreateTrimsName(): void
    {
        $authorName = 'Trimmed Author Test '.uniqid();

        // Créer avec espaces
        $author = $this->repository->findOrCreate('  '.$authorName.'  ');

        self::assertSame($authorName, $author->getName());

        // Vérifier qu'on retrouve le même auteur avec le nom non trimmé
        $sameAuthor = $this->repository->findOrCreate($authorName);
        self::assertSame($author->getId(), $sameAuthor->getId());

        // Nettoyer
        $this->em->remove($author);
        $this->em->flush();
    }

    /**
     * Teste findOrCreateMultiple avec plusieurs noms.
     */
    public function testFindOrCreateMultiple(): void
    {
        $suffix = uniqid();
        $names = [
            'Author One '.$suffix,
            'Author Two '.$suffix,
            'Author Three '.$suffix,
        ];

        $authors = $this->repository->findOrCreateMultiple($names);

        self::assertCount(3, $authors);
        self::assertSame('Author One '.$suffix, $authors[0]->getName());
        self::assertSame('Author Two '.$suffix, $authors[1]->getName());
        self::assertSame('Author Three '.$suffix, $authors[2]->getName());

        // Nettoyer
        foreach ($authors as $author) {
            $this->em->remove($author);
        }
        $this->em->flush();
    }

    /**
     * Teste findOrCreateMultiple filtre les noms vides.
     */
    public function testFindOrCreateMultipleFiltersEmptyNames(): void
    {
        $suffix = uniqid();
        $names = [
            'Valid Author '.$suffix,
            '',
            '   ',
            'Another Valid '.$suffix,
        ];

        $authors = $this->repository->findOrCreateMultiple($names);

        self::assertCount(2, $authors);
        $authorNames = \array_map(static fn (Author $a) => $a->getName(), $authors);
        self::assertContains('Valid Author '.$suffix, $authorNames);
        self::assertContains('Another Valid '.$suffix, $authorNames);

        // Nettoyer
        foreach ($authors as $author) {
            $this->em->remove($author);
        }
        $this->em->flush();
    }

    /**
     * Teste findOrCreateMultiple avec un mélange d'existants et nouveaux.
     */
    public function testFindOrCreateMultipleMixedExistingAndNew(): void
    {
        $suffix = uniqid();
        $existingName = 'Existing Multi '.$suffix;
        $newName = 'New Multi '.$suffix;

        // Créer un auteur existant
        $existing = new Author();
        $existing->setName($existingName);
        $this->em->persist($existing);
        $this->em->flush();
        $existingId = $existing->getId();

        $authors = $this->repository->findOrCreateMultiple([$existingName, $newName]);

        self::assertCount(2, $authors);

        // Vérifier que l'existant a le même ID
        $existingFound = \array_filter($authors, static fn (Author $a) => $a->getName() === $existingName);
        self::assertCount(1, $existingFound);
        self::assertSame($existingId, \array_values($existingFound)[0]->getId());

        // Nettoyer
        foreach ($authors as $author) {
            $this->em->remove($author);
        }
        $this->em->flush();
    }

    /**
     * Teste findOrCreateMultiple avec un tableau vide.
     */
    public function testFindOrCreateMultipleWithEmptyArray(): void
    {
        $authors = $this->repository->findOrCreateMultiple([]);

        self::assertCount(0, $authors);
    }

    /**
     * Teste findOrCreateMultiple avec seulement des noms vides.
     */
    public function testFindOrCreateMultipleWithOnlyEmptyNames(): void
    {
        $authors = $this->repository->findOrCreateMultiple(['', '   ', '']);

        self::assertCount(0, $authors);
    }
}
