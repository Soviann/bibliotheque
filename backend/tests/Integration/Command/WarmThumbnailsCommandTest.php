<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Message\WarmThumbnailsMessage;
use App\Service\Cover\ThumbnailGenerator;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

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

    public function testAsyncDispatchesMessagesInsteadOfGenerating(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ThumbnailGenerator&MockObject $thumbnailGenerator */
        $thumbnailGenerator = $this->createMock(ThumbnailGenerator::class);
        $container->set(ThumbnailGenerator::class, $thumbnailGenerator);

        // En mode async, rien n'est généré synchroniquement.
        $thumbnailGenerator->expects(self::never())
            ->method('generate');

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series1 = EntityFactory::createComicSeries('Async 1');
        $series1->setCoverImage('async1.webp');
        $em->persist($series1);

        $series2 = EntityFactory::createComicSeries('Async 2');
        $series2->setCoverImage('async2.webp');
        $em->persist($series2);

        $em->flush();

        $application = new Application(self::$kernel); // @phpstan-ignore argument.type
        $command = $application->find('app:warm-thumbnails');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--async' => true]);

        self::assertSame(0, $commandTester->getStatusCode());

        // Un message WarmThumbnailsMessage par couverture est mis en file
        // (le transport peut aussi contenir des messages déclenchés par la
        // création des séries — on ne compte que les nôtres).
        /** @var InMemoryTransport $transport */
        $transport = $container->get('messenger.transport.async');
        $warmMessages = \array_filter(
            $transport->getSent(),
            static fn (Envelope $envelope): bool => $envelope->getMessage() instanceof WarmThumbnailsMessage,
        );
        self::assertCount(2, $warmMessages);
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
