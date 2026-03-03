<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Entity\ComicSeries;
use App\Repository\AuthorRepository;

/**
 * Applique un LookupResult sur une ComicSeries (uniquement les champs null).
 */
class LookupApplier
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
    ) {
    }

    /**
     * Applique les données du résultat sur la série (champs null uniquement).
     *
     * @return list<string> Liste des champs mis à jour
     */
    public function apply(ComicSeries $series, LookupResult $result): array
    {
        $updatedFields = [];

        if (null === $series->getDescription() && null !== $result->description) {
            $series->setDescription($result->description);
            $updatedFields[] = 'description';
        }

        if (null === $series->getCoverUrl() && null !== $result->thumbnail) {
            $series->setCoverUrl($result->thumbnail);
            $updatedFields[] = 'coverUrl';
        }

        if (!$series->isOneShot() && true === $result->isOneShot) {
            $series->setIsOneShot(true);
            $updatedFields[] = 'isOneShot';
        }

        if (null === $series->getLatestPublishedIssue() && null !== $result->latestPublishedIssue) {
            $series->setLatestPublishedIssue($result->latestPublishedIssue);
            $updatedFields[] = 'latestPublishedIssue';
        }

        if (null === $series->getPublishedDate() && null !== $result->publishedDate) {
            $series->setPublishedDate($result->publishedDate);
            $updatedFields[] = 'publishedDate';
        }

        if (null === $series->getPublisher() && null !== $result->publisher) {
            $series->setPublisher($result->publisher);
            $updatedFields[] = 'publisher';
        }

        if ($series->getAuthors()->isEmpty() && null !== $result->authors) {
            $names = \array_map('trim', \explode(',', $result->authors));
            $authors = $this->authorRepository->findOrCreateMultiple($names);

            foreach ($authors as $author) {
                $series->addAuthor($author);
            }

            if ([] !== $authors) {
                $updatedFields[] = 'authors';
            }
        }

        return $updatedFields;
    }
}
