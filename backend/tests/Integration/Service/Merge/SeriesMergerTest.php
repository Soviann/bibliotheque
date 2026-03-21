<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Merge;

use App\DTO\MergePreview;
use App\DTO\MergePreviewTome;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Service\Merge\SeriesMerger;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration pour SeriesMerger.
 */
final class SeriesMergerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SeriesMerger $merger;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->merger = self::getContainer()->get(SeriesMerger::class);
    }

    public function testExecuteMergesSeriesInDatabase(): void
    {
        // Créer 3 séries avec des tomes
        $series1 = EntityFactory::createComicSeries('Astérix T1', type: ComicType::BD);
        $series1->setIsOneShot(true);
        $tome1 = EntityFactory::createTome(1, bought: true, read: true);
        $tome1->setTitle('Astérix le Gaulois');
        $tome1->setIsbn('978-2-0001-0001-1');
        $series1->addTome($tome1);

        $series2 = EntityFactory::createComicSeries('Astérix T2', type: ComicType::BD);
        $series2->setIsOneShot(true);
        $tome2 = EntityFactory::createTome(1, bought: true);
        $tome2->setTitle('La Serpe d\'or');
        $series2->addTome($tome2);

        $series3 = EntityFactory::createComicSeries('Astérix T3', type: ComicType::BD);
        $series3->setIsOneShot(true);
        $tome3 = EntityFactory::createTome(1, bought: true, downloaded: true, onNas: true);
        $tome3->setTitle('Astérix et les Goths');
        $series3->addTome($tome3);

        $this->em->persist($series1);
        $this->em->persist($series2);
        $this->em->persist($series3);
        $this->em->flush();

        $id1 = $series1->getId();
        $id2 = $series2->getId();
        $id3 = $series3->getId();

        // Construire l'aperçu de fusion
        $preview = new MergePreview(
            amazonUrl: null,
            authors: ['Goscinny', 'Uderzo'],
            coverUrl: 'https://example.com/asterix.jpg',
            defaultTomeBought: false,
            defaultTomeDownloaded: false,
            defaultTomeRead: false,
            description: 'Les aventures d\'Astérix le Gaulois',
            isOneShot: false,
            latestPublishedIssue: 40,
            latestPublishedIssueComplete: true,
            notInterestedBuy: false,
            notInterestedNas: false,
            publishedDate: null,
            publisher: 'Hachette',
            sourceSeriesIds: [$id1, $id2, $id3],
            status: 'buying',
            title: 'Astérix',
            tomes: [
                new MergePreviewTome(bought: true, downloaded: false, isbn: '978-2-0001-0001-1', number: 1, onNas: false, read: true, title: 'Astérix le Gaulois', tomeEnd: null),
                new MergePreviewTome(bought: true, downloaded: false, isbn: null, number: 2, onNas: false, read: false, title: 'La Serpe d\'or', tomeEnd: null),
                new MergePreviewTome(bought: true, downloaded: true, isbn: null, number: 3, onNas: true, read: false, title: 'Astérix et les Goths', tomeEnd: null),
            ],
            type: 'bd',
        );

        $this->merger->execute($preview);

        // Vider le cache Doctrine pour relire depuis la base
        $this->em->clear();

        // Vérifier qu'il ne reste qu'une seule série
        $repository = self::getContainer()->get(ComicSeriesRepository::class);
        $allSeries = $repository->findAll();
        self::assertCount(1, $allSeries);

        // Recharger la série fusionnée
        $merged = $repository->find($id1);
        self::assertNotNull($merged);

        // Vérifier les métadonnées
        self::assertSame('Astérix', $merged->getTitle());
        self::assertSame('Les aventures d\'Astérix le Gaulois', $merged->getDescription());
        self::assertSame('Hachette', $merged->getPublisher());
        self::assertSame('https://example.com/asterix.jpg', $merged->getCoverUrl());
        self::assertSame(ComicType::BD, $merged->getType());
        self::assertFalse($merged->isOneShot());
        self::assertSame(40, $merged->getLatestPublishedIssue());
        self::assertTrue($merged->isLatestPublishedIssueComplete());
        self::assertNotNull($merged->getMergeCheckedAt());

        // Vérifier les auteurs
        $authorNames = $merged->getAuthors()->map(static fn ($a) => $a->getName())->toArray();
        \sort($authorNames);
        self::assertSame(['Goscinny', 'Uderzo'], $authorNames);

        // Vérifier les tomes
        $tomes = $merged->getTomes()->toArray();
        self::assertCount(3, $tomes);

        // Tomes triés par numéro (OrderBy dans l'entité)
        self::assertSame(1, $tomes[0]->getNumber());
        self::assertSame('Astérix le Gaulois', $tomes[0]->getTitle());
        self::assertSame('978-2-0001-0001-1', $tomes[0]->getIsbn());
        self::assertTrue($tomes[0]->isBought());
        self::assertTrue($tomes[0]->isRead());

        self::assertSame(2, $tomes[1]->getNumber());
        self::assertSame('La Serpe d\'or', $tomes[1]->getTitle());
        self::assertTrue($tomes[1]->isBought());

        self::assertSame(3, $tomes[2]->getNumber());
        self::assertTrue($tomes[2]->isDownloaded());
        self::assertTrue($tomes[2]->isOnNas());

        // Vérifier que les séries secondaires sont supprimées
        self::assertNull($repository->find($id2));
        self::assertNull($repository->find($id3));
    }
}
