<?php

declare(strict_types=1);

namespace App\Service\Merge;

use App\DTO\MergePreview;
use App\DTO\MergePreviewTome;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\AuthorRepository;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Exécute la fusion de séries selon un aperçu validé.
 */
final readonly class SeriesMerger
{
    public function __construct(
        private AuthorRepository $authorRepository,
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Exécute la fusion de séries selon l'aperçu validé.
     */
    public function execute(MergePreview $preview): ComicSeries
    {
        // 1. Charger toutes les séries sources
        $sourceSeries = \array_map(
            fn (int $id): ComicSeries => $this->comicSeriesRepository->find($id)
                ?? throw new \InvalidArgumentException(\sprintf('Série introuvable : %d', $id)),
            $preview->sourceSeriesIds,
        );

        // 2. Série primaire = première série source
        $primary = $sourceSeries[0];

        // 3. Mettre à jour les métadonnées
        $primary->setAmazonUrl($preview->amazonUrl);
        $primary->setCoverUrl($preview->coverUrl);
        $primary->setDefaultTomeBought($preview->defaultTomeBought);
        $primary->setDefaultTomeDownloaded($preview->defaultTomeDownloaded);
        $primary->setDefaultTomeRead($preview->defaultTomeRead);
        $primary->setDescription($preview->description);
        $primary->setIsOneShot($preview->isOneShot);
        $primary->setLatestPublishedIssue($preview->latestPublishedIssue);
        $primary->setLatestPublishedIssueComplete($preview->latestPublishedIssueComplete);
        $primary->setNotInterestedBuy($preview->notInterestedBuy);
        $primary->setNotInterestedNas($preview->notInterestedNas);
        $primary->setPublishedDate($preview->publishedDate);
        $primary->setPublisher($preview->publisher);
        $primary->setStatus(ComicStatus::from($preview->status));
        $primary->setTitle($preview->title);
        $primary->setType(ComicType::from($preview->type));

        // 4. Gérer les auteurs
        foreach ($primary->getAuthors()->toArray() as $author) {
            $primary->removeAuthor($author);
        }

        $authors = $this->authorRepository->findOrCreateMultiple($preview->authors);
        foreach ($authors as $author) {
            $primary->addAuthor($author);
        }

        // 5. Gérer les tomes : supprimer les existants, recréer depuis l'aperçu
        $primary->getTomes()->clear();

        foreach ($preview->tomes as $previewTome) {
            $tome = $this->createTomeFromPreview($previewTome);
            $primary->addTome($tome);
        }

        // 6. Supprimer les séries secondaires
        $secondarySeries = \array_slice($sourceSeries, 1);
        foreach ($secondarySeries as $series) {
            $this->entityManager->remove($series);
        }

        // 7. Marquer la date de vérification de fusion
        $primary->setMergeCheckedAt(new \DateTimeImmutable());

        // 8. Persister
        $this->entityManager->flush();

        return $primary;
    }

    /**
     * Crée un Tome à partir d'un MergePreviewTome.
     */
    private function createTomeFromPreview(MergePreviewTome $previewTome): Tome
    {
        $tome = new Tome();
        $tome->setBought($previewTome->bought);
        $tome->setDownloaded($previewTome->downloaded);
        $tome->setIsbn($previewTome->isbn);
        $tome->setNumber($previewTome->number);
        $tome->setOnNas($previewTome->onNas);
        $tome->setRead($previewTome->read);
        $tome->setTitle($previewTome->title);
        $tome->setTomeEnd($previewTome->tomeEnd);

        return $tome;
    }
}
