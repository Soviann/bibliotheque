<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SeriesSuggestion;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Repository\SeriesSuggestionRepository;
use App\Service\Lookup\GeminiClientPool;
use App\Service\Lookup\GeminiJsonParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Génère des suggestions de séries similaires via Gemini.
 */
class SimilarSeriesService
{
    private const int BATCH_SIZE = 20;

    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GeminiClientPool $geminiClientPool,
        private readonly LoggerInterface $logger,
        private readonly SeriesSuggestionRepository $suggestionRepository,
    ) {
    }

    /**
     * @return \Generator<SeriesSuggestion>
     */
    public function generateSuggestions(): \Generator
    {
        $allSeries = $this->comicSeriesRepository->findAllForApi();
        $dismissedTitles = $this->suggestionRepository->findDismissedTitles();
        $existingTitles = \array_map(static fn ($s) => \mb_strtolower($s->title), $allSeries);

        $batches = \array_chunk($allSeries, self::BATCH_SIZE);

        foreach ($batches as $batch) {
            $seriesData = [];

            foreach ($batch as $item) {
                $seriesData[] = [
                    'authors' => $item->authors ?? '',
                    'publisher' => $item->publisher ?? '',
                    'title' => $item->title,
                    'type' => $item->type,
                ];
            }

            try {
                $suggestions = $this->querySuggestions($seriesData);

                foreach ($suggestions as $suggestion) {
                    $title = $suggestion['title'] ?? null;
                    $typeStr = $suggestion['type'] ?? null;

                    if (!\is_string($title) || !\is_string($typeStr)) {
                        continue;
                    }

                    $type = ComicType::tryFrom($typeStr);

                    if (null === $type) {
                        continue;
                    }

                    // Skip si déjà dans la bibliothèque
                    if (\in_array(\mb_strtolower($title), $existingTitles, true)) {
                        continue;
                    }

                    // Skip si déjà ignorée
                    if (\in_array($title, $dismissedTitles, true)) {
                        continue;
                    }

                    // Skip si déjà en attente
                    if ($this->suggestionRepository->existsPendingByTitleAndType($title, $type)) {
                        continue;
                    }

                    /** @var list<string> $authors */
                    $authors = \is_array($suggestion['authors'] ?? null) ? \array_values($suggestion['authors']) : [];
                    $reason = \is_string($suggestion['reason'] ?? null) ? $suggestion['reason'] : '';

                    $entity = new SeriesSuggestion(
                        authors: $authors,
                        reason: $reason,
                        sourceSeries: null,
                        title: $title,
                        type: $type,
                    );
                    $this->entityManager->persist($entity);

                    yield $entity;
                }

                $this->entityManager->flush();
            } catch (\Throwable $e) {
                $this->logger->error('Erreur Gemini pour les suggestions : {error}', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param list<array{authors: string, publisher: string, title: string, type: string}> $seriesData
     *
     * @return list<array<string, mixed>>
     */
    private function querySuggestions(array $seriesData): array
    {
        $seriesList = \implode("\n", \array_map(
            static fn (array $s) => \sprintf('- %s (%s) par %s', $s['title'], $s['type'], $s['authors']),
            $seriesData,
        ));

        $prompt = <<<PROMPT
            Voici une liste de séries de ma collection :
            {$seriesList}

            Suggère 5 séries similaires que je pourrais aimer, qui ne sont PAS dans cette liste.
            Retourne un tableau JSON avec exactement ce format :
            [{"title": "...", "type": "bd|manga|comics|livre", "authors": ["..."], "reason": "..."}]
            Retourne UNIQUEMENT le JSON, sans texte avant ou après.
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

        /** @var list<array<string, mixed>> $suggestions */
        $suggestions = $parsed;

        return $suggestions;
    }
}
