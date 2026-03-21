<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AuthorReleaseResult;
use App\Enum\ComicType;
use App\Enum\NotificationEntityType;
use App\Enum\NotificationType;
use App\Repository\AuthorRepository;
use App\Repository\ComicSeriesRepository;
use App\Repository\UserRepository;
use App\Service\Lookup\GeminiClientPool;
use App\Service\Lookup\GeminiJsonParser;
use Psr\Log\LoggerInterface;

/**
 * Vérifie si les auteurs suivis ont publié de nouvelles séries.
 */
class AuthorReleaseCheckerService
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly GeminiClientPool $geminiClientPool,
        private readonly LoggerInterface $logger,
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return \Generator<AuthorReleaseResult>
     */
    public function check(bool $dryRun = false): \Generator
    {
        $followedAuthors = $this->authorRepository->findFollowed();
        $user = $this->userRepository->findOneBy([]);

        if (null === $user || [] === $followedAuthors) {
            return;
        }

        $allTitles = \array_map(
            static fn ($s) => \mb_strtolower($s->title),
            $this->comicSeriesRepository->findAllForApi(),
        );

        foreach ($followedAuthors as $author) {
            try {
                /** @var list<string> $knownSeries */
                $knownSeries = \array_values($author->getComicSeries()->map(
                    static fn ($s) => $s->getTitle(),
                )->toArray());

                $newSeries = $this->queryNewSeries($author->getName(), $knownSeries);

                foreach ($newSeries as $series) {
                    $title = $series['title'] ?? null;
                    $typeStr = $series['type'] ?? null;

                    if (!\is_string($title) || !\is_string($typeStr)) {
                        continue;
                    }

                    $type = ComicType::tryFrom($typeStr) ?? ComicType::BD;

                    // Skip si déjà dans la bibliothèque
                    if (\in_array(\mb_strtolower($title), $allTitles, true)) {
                        continue;
                    }

                    $result = new AuthorReleaseResult(
                        authorName: $author->getName(),
                        newSeriesTitle: $title,
                        type: $type,
                    );

                    if (!$dryRun) {
                        $this->notificationService->create(
                            user: $user,
                            type: NotificationType::AUTHOR_NEW_SERIES,
                            title: \sprintf('Nouvelle série de %s', $author->getName()),
                            message: \sprintf('%s a publié « %s »', $author->getName(), $title),
                            relatedEntityType: NotificationEntityType::AUTHOR,
                            relatedEntityId: $author->getId(),
                        );
                    }

                    yield $result;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Erreur vérification auteur {author} : {error}', [
                    'author' => $author->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param list<string> $knownSeries
     *
     * @return list<array<string, mixed>>
     */
    private function queryNewSeries(string $authorName, array $knownSeries): array
    {
        $knownList = \implode(', ', $knownSeries);

        $prompt = <<<PROMPT
            L'auteur "{$authorName}" a les séries suivantes dans ma collection : {$knownList}

            Quelles autres séries (BD, manga, comics, livres) cet auteur a-t-il publiées qui ne sont PAS dans cette liste ?
            Retourne un tableau JSON : [{"title": "...", "type": "bd|manga|comics|livre"}]
            Retourne UNIQUEMENT le JSON, sans texte.
            PROMPT;

        /** @var string $response */
        $response = $this->geminiClientPool->executeWithRetry(
            static fn ($client, \BackedEnum|string $model) => $client
                ->generativeModel(model: $model)
                ->generateContent($prompt)
                ->text(),
        );

        $parsed = GeminiJsonParser::parseJsonFromText($response);

        if (!\is_array($parsed)) {
            return [];
        }

        /** @var list<array<string, mixed>> */
        return $parsed;
    }
}
