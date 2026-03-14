<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Repository\AuthorRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Applique un LookupResult sur une ComicSeries (uniquement les champs null).
 */
class LookupApplier
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly HttpClientInterface $httpClient,
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

        if (null === $series->getAmazonUrl() && null !== $result->amazonUrl && $this->isUrlReachable($result->amazonUrl)) {
            $series->setAmazonUrl($result->amazonUrl);
            $updatedFields[] = 'amazonUrl';
        }

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
            $series->setLatestPublishedIssueUpdatedAt(new \DateTimeImmutable());
            $updatedFields[] = 'latestPublishedIssue';

            $this->createMissingTomes($series, $result->latestPublishedIssue);
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

    /**
     * Vérifie qu'une URL est accessible via une requête HEAD.
     */
    private function isUrlReachable(string $url): bool
    {
        try {
            $response = $this->httpClient->request('HEAD', $url);

            return $response->getStatusCode() < 400;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Crée les tomes manquants (1 → latestPublishedIssue) avec les flags par défaut de la série.
     */
    private function createMissingTomes(ComicSeries $series, int $latestPublishedIssue): void
    {
        $existingNumbers = [];
        foreach ($series->getTomes() as $tome) {
            $existingNumbers[$tome->getNumber()] = true;
        }

        for ($number = 1; $number <= $latestPublishedIssue; ++$number) {
            if (isset($existingNumbers[$number])) {
                continue;
            }

            $tome = new Tome();
            $tome->setBought($series->isDefaultTomeBought());
            $tome->setDownloaded($series->isDefaultTomeDownloaded());
            $tome->setNumber($number);
            $tome->setRead($series->isDefaultTomeRead());
            $series->addTome($tome);
        }
    }
}
