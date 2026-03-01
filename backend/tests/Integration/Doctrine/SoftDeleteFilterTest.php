<?php

declare(strict_types=1);

namespace App\Tests\Integration\Doctrine;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'integration pour le filtre Doctrine SoftDeleteFilter.
 */
final class SoftDeleteFilterTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testFilterEnabledExcludesSoftDeletedSeries(): void
    {
        $active = EntityFactory::createComicSeries('Active');
        $deleted = EntityFactory::createComicSeries('Deleted');

        $this->em->persist($active);
        $this->em->persist($deleted);
        $this->em->flush();

        // Soft-delete la serie
        $deleted->delete();
        $this->em->flush();
        $this->em->clear();

        // Le filtre est active par defaut
        self::assertTrue($this->em->getFilters()->isEnabled('soft_delete'));

        $result = $this->em->getRepository(ComicSeries::class)->findAll();

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $result);
        self::assertContains('Active', $titles);
        self::assertNotContains('Deleted', $titles);
    }

    public function testFilterDisabledReturnsSoftDeletedSeries(): void
    {
        $active = EntityFactory::createComicSeries('Active');
        $deleted = EntityFactory::createComicSeries('Deleted');

        $this->em->persist($active);
        $this->em->persist($deleted);
        $this->em->flush();

        $deleted->delete();
        $this->em->flush();
        $this->em->clear();

        // Desactiver le filtre
        $this->em->getFilters()->disable('soft_delete');

        $result = $this->em->getRepository(ComicSeries::class)->findAll();

        $titles = \array_map(static fn (ComicSeries $s): string => $s->getTitle(), $result);
        self::assertContains('Active', $titles);
        self::assertContains('Deleted', $titles);

        // Reactiver pour ne pas affecter les autres tests
        $this->em->getFilters()->enable('soft_delete');
    }

    public function testFilterDoesNotAffectNonSoftDeletableEntities(): void
    {
        // Tome n'implemente pas SoftDeletableInterface :
        // le filtre ne doit pas l'affecter
        $series = EntityFactory::createComicSeries('Series');
        $tome = EntityFactory::createTome(1);
        $series->addTome($tome);

        $this->em->persist($series);
        $this->em->flush();

        $tomeId = $tome->getId();
        self::assertNotNull($tomeId);

        $this->em->clear();

        // Le filtre est active mais Tome est toujours accessible
        self::assertTrue($this->em->getFilters()->isEnabled('soft_delete'));

        $found = $this->em->getRepository(Tome::class)->find($tomeId);
        self::assertInstanceOf(Tome::class, $found);
        self::assertSame(1, $found->getNumber());
    }
}
