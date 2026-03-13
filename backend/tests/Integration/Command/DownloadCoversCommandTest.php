<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\ComicSeries;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'intégration pour la commande app:download-covers.
 */
final class DownloadCoversCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('app:download-covers');
        $this->commandTester = new CommandTester($command);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testDryRunDoesNotModifyEntities(): void
    {
        // Crée une série avec coverUrl mais sans coverImage
        $series = new ComicSeries();
        $series->setTitle('Test DryRun');
        $series->setCoverUrl('https://example.com/cover.jpg');
        $this->em->persist($series);
        $this->em->flush();

        $this->commandTester->execute(['--dry-run' => true, '--limit' => 1]);
        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('dry-run', \strtolower($output));
        self::assertSame(0, $this->commandTester->getStatusCode());

        // La série ne doit pas avoir de coverImage
        $this->em->refresh($series);
        self::assertNull($series->getCoverImage());
    }

    public function testLimitOptionLimitsProcessing(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $series = new ComicSeries();
            $series->setTitle(\sprintf('Limit Test %d', $i));
            $series->setCoverUrl(\sprintf('https://example.com/cover%d.jpg', $i));
            $this->em->persist($series);
        }
        $this->em->flush();

        $this->commandTester->execute(['--dry-run' => true, '--limit' => 2]);
        $output = $this->commandTester->getDisplay();

        self::assertSame(0, $this->commandTester->getStatusCode());
        // Doit traiter au maximum 2 séries
        self::assertStringContainsString('2', $output);
    }

    public function testSuccessMessageWhenNothingToProcess(): void
    {
        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Aucune', $output);
    }
}
