<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\ComicSeries;
use App\Enum\ComicType;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'intégration pour la commande app:lookup-missing.
 */
final class LookupMissingCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $application = new Application(self::$kernel);
        $command = $application->find('app:lookup-missing');
        $this->commandTester = new CommandTester($command);
    }

    public function testDryRunDoesNotModifyDatabase(): void
    {
        $series = EntityFactory::createComicSeries('Test Series');
        $this->em->persist($series);
        $this->em->flush();

        $this->commandTester->execute(['--dry-run' => true, '--delay' => 0]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('dry-run', $this->commandTester->getDisplay());

        // Vérifier que lookupCompletedAt n'a pas été mis à jour
        $this->em->clear();
        $refreshed = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Test Series']);
        self::assertNull($refreshed->getLookupCompletedAt());
    }

    public function testLimitRestrictsNumberOfLookups(): void
    {
        $this->em->persist(EntityFactory::createComicSeries('Alpha'));
        $this->em->persist(EntityFactory::createComicSeries('Bravo'));
        $this->em->persist(EntityFactory::createComicSeries('Charlie'));
        $this->em->flush();

        $this->commandTester->execute(['--limit' => 1, '--delay' => 0]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('1 série(s) traitée(s)', $display);
    }

    public function testTypeFilterRestrictsToSpecificType(): void
    {
        $manga = EntityFactory::createComicSeries('Naruto', type: ComicType::MANGA);
        $bd = EntityFactory::createComicSeries('Asterix', type: ComicType::BD);

        $this->em->persist($bd);
        $this->em->persist($manga);
        $this->em->flush();

        $this->commandTester->execute(['--type' => 'manga', '--delay' => 0]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Naruto', $display);
        self::assertStringNotContainsString('Asterix', $display);
    }

    public function testSkipsSeriesWithLookupCompletedAt(): void
    {
        $already = EntityFactory::createComicSeries('Already Done');
        $already->setLookupCompletedAt(new \DateTimeImmutable());
        $pending = EntityFactory::createComicSeries('Pending');

        $this->em->persist($already);
        $this->em->persist($pending);
        $this->em->flush();

        $this->commandTester->execute(['--delay' => 0]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Pending', $display);
        self::assertStringNotContainsString('Already Done', $display);
    }

    public function testForceIgnoresLookupCompletedAt(): void
    {
        $already = EntityFactory::createComicSeries('Already Done');
        $already->setLookupCompletedAt(new \DateTimeImmutable());

        $this->em->persist($already);
        $this->em->flush();

        $this->commandTester->execute(['--force' => true, '--delay' => 0]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Already Done', $display);
    }

    public function testNoSeriesFoundDisplaysMessage(): void
    {
        $this->commandTester->execute(['--delay' => 0]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Aucune série', $display);
    }

    public function testSeriesIdOptionTargetsSpecificSeries(): void
    {
        $alpha = EntityFactory::createComicSeries('Alpha');
        $bravo = EntityFactory::createComicSeries('Bravo');

        $this->em->persist($alpha);
        $this->em->persist($bravo);
        $this->em->flush();

        $this->commandTester->execute(['--series' => $alpha->getId(), '--delay' => 0]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Alpha', $display);
        self::assertStringNotContainsString('Bravo', $display);
    }

    public function testInvalidSeriesIdReturnsFailure(): void
    {
        $this->commandTester->execute(['--series' => 99999, '--delay' => 0]);

        self::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('introuvable', $display);
    }
}
