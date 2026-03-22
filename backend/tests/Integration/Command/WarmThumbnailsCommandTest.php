<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Service\Cover\ThumbnailGenerator;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'intégration pour la commande app:warm-thumbnails.
 */
final class WarmThumbnailsCommandTest extends KernelTestCase
{
    public function testExecuteGeneratesThumbnailsForAllCovers(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ThumbnailGenerator&MockObject $thumbnailGenerator */
        $thumbnailGenerator = $this->createMock(ThumbnailGenerator::class);
        $container->set(ThumbnailGenerator::class, $thumbnailGenerator);

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Crée 2 séries avec couverture locale et 1 sans
        $series1 = EntityFactory::createComicSeries('Avec couverture 1');
        $series1->setCoverImage('cover1.webp');
        $em->persist($series1);

        $series2 = EntityFactory::createComicSeries('Avec couverture 2');
        $series2->setCoverImage('cover2.webp');
        $em->persist($series2);

        $series3 = EntityFactory::createComicSeries('Sans couverture');
        $em->persist($series3);

        $em->flush();

        $thumbnailGenerator->expects(self::exactly(2))
            ->method('generate');

        $application = new Application(self::$kernel); // @phpstan-ignore argument.type
        $command = $application->find('app:warm-thumbnails');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('2', $commandTester->getDisplay());
    }

    public function testDryRunDoesNotGenerateThumbnails(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ThumbnailGenerator&MockObject $thumbnailGenerator */
        $thumbnailGenerator = $this->createMock(ThumbnailGenerator::class);
        $container->set(ThumbnailGenerator::class, $thumbnailGenerator);

        $thumbnailGenerator->expects(self::never())
            ->method('generate');

        $application = new Application(self::$kernel); // @phpstan-ignore argument.type
        $command = $application->find('app:warm-thumbnails');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--dry-run' => true]);

        self::assertSame(0, $commandTester->getStatusCode());
    }
}
