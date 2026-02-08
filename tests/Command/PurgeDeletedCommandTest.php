<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\ComicSeries;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'intégration pour PurgeDeletedCommand.
 */
class PurgeDeletedCommandTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Teste la purge des séries supprimées depuis plus de N jours.
     */
    public function testPurgesSeriesOlderThanDays(): void
    {
        // Créer et soft-delete une série avec une date ancienne
        $series = new ComicSeries();
        $series->setTitle('Série Ancienne Purge');
        $this->em->persist($series);
        $this->em->flush();

        $seriesId = $series->getId();

        $this->em->remove($series);
        $this->em->flush();

        // Mettre la date de suppression à 31 jours dans le passé
        $this->connection->executeStatement(
            'UPDATE comic_series SET deleted_at = ? WHERE id = ?',
            [(new \DateTime('-31 days'))->format('Y-m-d H:i:s'), $seriesId]
        );

        $application = new Application(self::$kernel);
        $command = $application->find('app:purge-deleted');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--days' => 30]);
        $commandTester->assertCommandIsSuccessful();

        // Vérifier que la série a été supprimée
        $row = $this->connection->fetchAssociative(
            'SELECT id FROM comic_series WHERE id = ?',
            [$seriesId]
        );
        self::assertFalse($row, 'La série purgée ne doit plus exister en base');
    }

    /**
     * Teste que les séries récemment supprimées ne sont pas purgées.
     */
    public function testDoesNotPurgeRecentlyDeleted(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Série Récente Purge');
        $this->em->persist($series);
        $this->em->flush();

        $seriesId = $series->getId();

        $this->em->remove($series);
        $this->em->flush();

        // deleted_at est déjà "maintenant", donc < 30 jours

        $application = new Application(self::$kernel);
        $command = $application->find('app:purge-deleted');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--days' => 30]);
        $commandTester->assertCommandIsSuccessful();

        // Vérifier que la série existe toujours
        $row = $this->connection->fetchAssociative(
            'SELECT id FROM comic_series WHERE id = ?',
            [$seriesId]
        );
        self::assertNotFalse($row, 'La série récemment supprimée ne doit pas être purgée');
    }

    /**
     * Teste que --dry-run ne supprime pas.
     */
    public function testDryRunDoesNotDelete(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Série Dry Run Purge');
        $this->em->persist($series);
        $this->em->flush();

        $seriesId = $series->getId();

        $this->em->remove($series);
        $this->em->flush();

        // Mettre la date de suppression à 31 jours dans le passé
        $this->connection->executeStatement(
            'UPDATE comic_series SET deleted_at = ? WHERE id = ?',
            [(new \DateTime('-31 days'))->format('Y-m-d H:i:s'), $seriesId]
        );

        $application = new Application(self::$kernel);
        $command = $application->find('app:purge-deleted');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--days' => 30, '--dry-run' => true]);
        $commandTester->assertCommandIsSuccessful();

        // Vérifier que la série existe toujours
        $row = $this->connection->fetchAssociative(
            'SELECT id FROM comic_series WHERE id = ?',
            [$seriesId]
        );
        self::assertNotFalse($row, 'La série ne doit pas être supprimée en mode dry-run');

        // Vérifier que le message indique le nombre de séries éligibles
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Série Dry Run Purge', $output);
    }

    /**
     * Teste qu'une valeur de --days invalide retourne FAILURE.
     */
    public function testInvalidDaysReturnFailure(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:purge-deleted');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--days' => 0]);

        self::assertSame(1, $exitCode);
    }
}
