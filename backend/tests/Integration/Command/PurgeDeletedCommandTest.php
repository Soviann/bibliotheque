<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\ComicSeries;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'integration pour la commande app:purge-deleted.
 */
final class PurgeDeletedCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $application = new Application(self::$kernel);
        $command = $application->find('app:purge-deleted');
        $this->commandTester = new CommandTester($command);
    }

    public function testNoDeletedSeriesOutputsMessage(): void
    {
        $active = EntityFactory::createComicSeries('Active Series');
        $this->em->persist($active);
        $this->em->flush();

        $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Aucune série à purger', $this->commandTester->getDisplay());
    }

    public function testDeletedSeriesOlderThanDaysArePurged(): void
    {
        // Serie supprimee il y a 60 jours
        $oldDeleted = EntityFactory::createComicSeries('Old Deleted');
        $this->em->persist($oldDeleted);
        $this->em->flush();

        // Soft-delete manuellement avec une date ancienne
        $oldDeleted->delete();
        $this->em->flush();

        // Modifier la date de suppression en base pour simuler 60 jours
        $this->em->getConnection()->executeStatement(
            'UPDATE comic_series SET deleted_at = :date WHERE id = :id',
            [
                'date' => (new \DateTime('-60 days'))->format('Y-m-d H:i:s'),
                'id' => $oldDeleted->getId(),
            ]
        );

        $this->commandTester->execute(['--days' => '30']);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('purgée(s)', $this->commandTester->getDisplay());

        // Verifier que la serie a ete supprimee definitivement
        $this->em->getFilters()->disable('soft_delete');
        $this->em->clear();
        $remaining = $this->em->getRepository(ComicSeries::class)->find($oldDeleted->getId());
        self::assertNull($remaining);
        $this->em->getFilters()->enable('soft_delete');
    }

    public function testDeletedSeriesNewerThanDaysAreNotPurged(): void
    {
        // Serie supprimee il y a 10 jours (inferieur au seuil de 30)
        $recentDeleted = EntityFactory::createComicSeries('Recent Deleted');
        $this->em->persist($recentDeleted);
        $this->em->flush();

        $recentDeleted->delete();
        $this->em->flush();

        // Modifier la date pour simuler 10 jours
        $this->em->getConnection()->executeStatement(
            'UPDATE comic_series SET deleted_at = :date WHERE id = :id',
            [
                'date' => (new \DateTime('-10 days'))->format('Y-m-d H:i:s'),
                'id' => $recentDeleted->getId(),
            ]
        );

        $this->commandTester->execute(['--days' => '30']);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Aucune série à purger', $this->commandTester->getDisplay());

        // Verifier que la serie existe toujours
        $this->em->getFilters()->disable('soft_delete');
        $this->em->clear();
        $still = $this->em->getRepository(ComicSeries::class)->find($recentDeleted->getId());
        self::assertNotNull($still);
        $this->em->getFilters()->enable('soft_delete');
    }

    public function testDryRunListsButDoesNotDelete(): void
    {
        $series = EntityFactory::createComicSeries('To Purge Dry');
        $this->em->persist($series);
        $this->em->flush();

        $series->delete();
        $this->em->flush();

        $seriesId = $series->getId();

        // Date ancienne
        $this->em->getConnection()->executeStatement(
            'UPDATE comic_series SET deleted_at = :date WHERE id = :id',
            [
                'date' => (new \DateTime('-60 days'))->format('Y-m-d H:i:s'),
                'id' => $seriesId,
            ]
        );

        $this->commandTester->execute([
            '--days' => '30',
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('dry-run', $this->commandTester->getDisplay());
        self::assertStringContainsString('To Purge Dry', $this->commandTester->getDisplay());

        // Verifier que la serie existe toujours
        $this->em->getFilters()->disable('soft_delete');
        $this->em->clear();
        $still = $this->em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNotNull($still);
        $this->em->getFilters()->enable('soft_delete');
    }

    public function testInvalidDaysZeroReturnsFailure(): void
    {
        $this->commandTester->execute(['--days' => '0']);

        self::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString('supérieur à 0', $this->commandTester->getDisplay());
    }

    public function testInvalidDaysNegativeReturnsFailure(): void
    {
        $this->commandTester->execute(['--days' => '-1']);

        self::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString('supérieur à 0', $this->commandTester->getDisplay());
    }

    public function testMultipleSeriesEligibleForPurge(): void
    {
        $series1 = EntityFactory::createComicSeries('Series One');
        $series2 = EntityFactory::createComicSeries('Series Two');
        $this->em->persist($series1);
        $this->em->persist($series2);
        $this->em->flush();

        $series1->delete();
        $series2->delete();
        $this->em->flush();

        $id1 = $series1->getId();
        $id2 = $series2->getId();

        // Modifier la date de suppression pour les deux
        $this->em->getConnection()->executeStatement(
            'UPDATE comic_series SET deleted_at = :date WHERE id IN (:id1, :id2)',
            [
                'date' => (new \DateTime('-60 days'))->format('Y-m-d H:i:s'),
                'id1' => $id1,
                'id2' => $id2,
            ]
        );

        $this->commandTester->execute(['--days' => '30']);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('2 série(s) purgée(s)', $this->commandTester->getDisplay());

        // Verifier que les deux series ont ete supprimees
        $this->em->getFilters()->disable('soft_delete');
        $this->em->clear();
        self::assertNull($this->em->getRepository(ComicSeries::class)->find($id1));
        self::assertNull($this->em->getRepository(ComicSeries::class)->find($id2));
        $this->em->getFilters()->enable('soft_delete');
    }
}
