<?php

declare(strict_types=1);

namespace App\Tests\Doctrine\Filter;

use App\Entity\ComicSeries;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration pour le filtre SQL soft delete.
 */
class SoftDeleteFilterTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Teste qu'une série soft-deleted est exclue des requêtes avec le filtre actif.
     */
    public function testSoftDeletedSeriesIsExcludedFromQueries(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Série Soft Deleted Filter Test');
        $this->em->persist($series);
        $this->em->flush();

        $seriesId = $series->getId();

        // Soft-delete la série
        $this->em->remove($series);
        $this->em->flush();
        $this->em->clear();

        // Avec le filtre actif, find() ne doit pas la trouver
        $found = $this->em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNull($found, 'Une série soft-deleted ne doit pas être trouvée avec le filtre actif');
    }

    /**
     * Teste qu'une série soft-deleted est visible après désactivation du filtre.
     */
    public function testSoftDeletedSeriesIsVisibleWhenFilterDisabled(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Série Visible Sans Filtre');
        $this->em->persist($series);
        $this->em->flush();

        $seriesId = $series->getId();

        // Soft-delete la série
        $this->em->remove($series);
        $this->em->flush();
        $this->em->clear();

        // Désactiver le filtre
        $this->em->getFilters()->disable('soft_delete');

        $found = $this->em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNotNull($found, 'Une série soft-deleted doit être visible sans le filtre');
        self::assertNotNull($found->getDeletedAt());

        // Réactiver le filtre
        $this->em->getFilters()->enable('soft_delete');
    }

    /**
     * Teste qu'une série non supprimée est toujours visible avec le filtre actif.
     */
    public function testActiveSeriesIsVisibleWithFilterEnabled(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Série Active Filter Test');
        $this->em->persist($series);
        $this->em->flush();

        $seriesId = $series->getId();
        $this->em->clear();

        $found = $this->em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNotNull($found, 'Une série active doit être visible avec le filtre actif');
        self::assertNull($found->getDeletedAt());
    }
}
