<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Tome;
use App\Repository\TomeRepository;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'integration pour TomeRepository (CRUD basique).
 */
final class TomeRepositoryTest extends KernelTestCase
{
    private TomeRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(TomeRepository::class);
    }

    public function testPersistAndFindTome(): void
    {
        $series = EntityFactory::createComicSeries('Naruto');
        $tome = EntityFactory::createTome(1, bought: true, downloaded: true, onNas: true, read: true);
        $tome->setIsbn('978-2-505-00001-0');
        $tome->setTitle('Le debut');
        $series->addTome($tome);

        $this->em->persist($series);
        $this->em->flush();

        $tomeId = $tome->getId();
        self::assertNotNull($tomeId);

        $this->em->clear();

        $found = $this->repository->find($tomeId);

        self::assertInstanceOf(Tome::class, $found);
        self::assertSame(1, $found->getNumber());
        self::assertTrue($found->isBought());
        self::assertTrue($found->isDownloaded());
        self::assertTrue($found->isOnNas());
        self::assertTrue($found->isRead());
        self::assertSame('978-2-505-00001-0', $found->getIsbn());
        self::assertSame('Le debut', $found->getTitle());
    }

    public function testFindByComicSeries(): void
    {
        $series = EntityFactory::createComicSeries('One Piece');
        $series->addTome(EntityFactory::createTome(1));
        $series->addTome(EntityFactory::createTome(2));
        $series->addTome(EntityFactory::createTome(3));

        $otherSeries = EntityFactory::createComicSeries('Bleach');
        $otherSeries->addTome(EntityFactory::createTome(1));

        $this->em->persist($otherSeries);
        $this->em->persist($series);
        $this->em->flush();

        $tomes = $this->repository->createQueryBuilder('t')
            ->where('t.comicSeries = :series')
            ->setParameter('series', $series)
            ->orderBy('t.number', 'ASC')
            ->getQuery()
            ->getResult();

        self::assertCount(3, $tomes);
        self::assertSame(1, $tomes[0]->getNumber());
        self::assertSame(2, $tomes[1]->getNumber());
        self::assertSame(3, $tomes[2]->getNumber());
    }

    public function testOrphanRemovalDeletesTomeWhenRemovedFromCollection(): void
    {
        $series = EntityFactory::createComicSeries('Dragon Ball');
        $tome1 = EntityFactory::createTome(1);
        $tome2 = EntityFactory::createTome(2);
        $series->addTome($tome1);
        $series->addTome($tome2);

        $this->em->persist($series);
        $this->em->flush();

        $tome1Id = $tome1->getId();
        self::assertNotNull($tome1Id);

        // Retirer le tome de la collection => orphanRemoval le supprime
        $series->removeTome($tome1);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->repository->find($tome1Id));
    }
}
