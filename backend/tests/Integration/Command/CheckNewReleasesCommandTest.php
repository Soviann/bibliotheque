<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Enum\ComicStatus;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupResult;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'intégration pour la commande app:check-new-releases.
 */
final class CheckNewReleasesCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Mock le LookupOrchestrator pour éviter les vrais appels API
        $orchestrator = $this->createMock(LookupOrchestrator::class);
        $orchestrator->method('lookupByTitle')->willReturn(
            new LookupResult(latestPublishedIssue: 15),
        );
        $orchestrator->method('getLastApiMessages')->willReturn([]);

        static::getContainer()->set(LookupOrchestrator::class, $orchestrator);

        $application = new Application(self::$kernel);
        $command = $application->find('app:check-new-releases');
        $this->commandTester = new CommandTester($command);
    }

    public function testNoSeriesOutputsMessage(): void
    {
        // Pas de série BUYING → rien à vérifier
        $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Aucune série à vérifier', $this->commandTester->getDisplay());
    }

    public function testWithBuyingSeriesShowsProgress(): void
    {
        $series = EntityFactory::createComicSeries('Naruto');
        $series->setLatestPublishedIssue(10);
        for ($i = 1; $i <= 10; ++$i) {
            $series->addTome(EntityFactory::createTome($i));
        }

        $this->em->persist($series);
        $this->em->flush();

        $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Naruto', $display);
        self::assertStringContainsString('1 série(s) vérifiée(s)', $display);
    }

    public function testDryRunDoesNotPersist(): void
    {
        $series = EntityFactory::createComicSeries('Test');
        $series->setLatestPublishedIssue(5);

        $this->em->persist($series);
        $this->em->flush();

        $this->commandTester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('dry-run', $this->commandTester->getDisplay());

        // Vérifier que newReleasesCheckedAt n'a pas été mis à jour
        $this->em->clear();
        $refreshed = $this->em->getRepository(\App\Entity\ComicSeries::class)->find($series->getId());
        self::assertNull($refreshed->getNewReleasesCheckedAt());
    }

    public function testLimitOption(): void
    {
        $this->em->persist(EntityFactory::createComicSeries('Alpha'));
        $this->em->persist(EntityFactory::createComicSeries('Bravo'));
        $this->em->persist(EntityFactory::createComicSeries('Charlie'));
        $this->em->flush();

        $this->commandTester->execute(['--limit' => '1']);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('1 série(s) vérifiée(s)', $display);
    }

    public function testExcludesFinishedAndOneShotSeries(): void
    {
        $finished = EntityFactory::createComicSeries('Finished', ComicStatus::FINISHED);

        $oneShot = EntityFactory::createComicSeries('One Shot');
        $oneShot->setIsOneShot(true);

        $complete = EntityFactory::createComicSeries('Complete');
        $complete->setLatestPublishedIssueComplete(true);

        $this->em->persist($complete);
        $this->em->persist($finished);
        $this->em->persist($oneShot);
        $this->em->flush();

        $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Aucune série à vérifier', $this->commandTester->getDisplay());
    }
}
