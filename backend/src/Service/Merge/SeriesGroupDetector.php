<?php

declare(strict_types=1);

namespace App\Service\Merge;

use App\DTO\MergeGroup;
use App\DTO\MergeGroupEntry;
use App\Entity\ComicSeries;
use Gemini\Contracts\ClientContract as GeminiClient;
use Gemini\Data\GoogleSearch;
use Gemini\Data\Tool;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Détecte les groupes de séries qui devraient être fusionnées via Gemini.
 */
class SeriesGroupDetector
{
    private const string MODEL = 'gemini-2.5-flash';
    private const int MAX_BATCH_SIZE = 50;

    public function __construct(
        private readonly GeminiClient $geminiClient,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $limiterFactory,
    ) {
    }

    /**
     * Détecte les groupes de séries qui devraient être fusionnées.
     *
     * @param ComicSeries[] $seriesList
     *
     * @return list<MergeGroup>
     */
    public function detect(array $seriesList): array
    {
        $batches = $this->buildBatches($seriesList);
        $allGroups = [];

        foreach ($batches as $batch) {
            $groups = $this->processBatch($batch);
            \array_push($allGroups, ...$groups);
        }

        return $allGroups;
    }

    /**
     * Regroupe les séries par première lettre (alphabétique).
     * Les titres commençant par un chiffre sont regroupés ensemble.
     * Les batches dépassant MAX_BATCH_SIZE sont découpés.
     *
     * @param ComicSeries[] $seriesList
     *
     * @return list<list<ComicSeries>>
     */
    private function buildBatches(array $seriesList): array
    {
        $groups = [];

        foreach ($seriesList as $series) {
            $firstChar = \mb_strtoupper(\mb_substr($series->getTitle(), 0, 1));
            $key = \is_numeric($firstChar) ? '0-9' : $firstChar;
            $groups[$key][] = $series;
        }

        \ksort($groups);

        $batches = [];
        foreach ($groups as $group) {
            if (\count($group) <= self::MAX_BATCH_SIZE) {
                $batches[] = $group;
            } else {
                foreach (\array_chunk($group, self::MAX_BATCH_SIZE) as $chunk) {
                    $batches[] = $chunk;
                }
            }
        }

        return $batches;
    }

    /**
     * Traite un batch de séries : appel Gemini + parsing de la réponse.
     *
     * @param ComicSeries[] $batch
     *
     * @return list<MergeGroup>
     */
    private function processBatch(array $batch): array
    {
        $limiter = $this->limiterFactory->create('gemini_global');
        if (!$limiter->consume()->isAccepted()) {
            $this->logger->warning('Rate limit atteint pour la détection de groupes de séries.');

            return [];
        }

        /** @var array<int, ComicSeries> $seriesMap */
        $seriesMap = [];
        foreach ($batch as $series) {
            $seriesMap[(int) $series->getId()] = $series;
        }

        $prompt = $this->buildPrompt($batch);

        try {
            $response = $this->geminiClient
                ->generativeModel(model: self::MODEL)
                ->withTool(new Tool(googleSearch: GoogleSearch::from()))
                ->generateContent($prompt);

            $text = $response->text();
        } catch (\Throwable $e) {
            $this->logger->error('Erreur Gemini lors de la détection de groupes : {message}', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        $data = $this->parseJsonFromText($text);
        if (null === $data) {
            $this->logger->warning('Réponse Gemini non parseable pour la détection de groupes.', [
                'response' => $text,
            ]);

            return [];
        }

        return $this->buildMergeGroups($data, $seriesMap);
    }

    /**
     * Construit le prompt Gemini pour un batch de séries.
     *
     * @param ComicSeries[] $batch
     */
    private function buildPrompt(array $batch): string
    {
        $lines = [];
        foreach ($batch as $series) {
            $lines[] = \sprintf('- %s (ID: %d)', $series->getTitle(), $series->getId());
        }

        $titlesList = \implode("\n", $lines);

        return <<<PROMPT
            Tu es un expert en bandes dessinées, comics et mangas.
            Voici une liste de titres de séries de ma bibliothèque. Certains sont en réalité des tomes d'une même série (par exemple "Astérix - Astérix chez les bretons" est un tome de la série "Astérix").

            Identifie les groupes de titres qui appartiennent à la même série.

            Pour chaque groupe, indique :
            - "title": le nom canonique de la série
            - "entries": un tableau avec pour chaque titre : "id" (l'ID fourni), "tomeNumber" (le numéro du tome dans la série, ou null si inconnu)

            Ne crée PAS de groupe pour les titres qui sont des séries indépendantes.
            Réponds UNIQUEMENT en JSON valide, sous la forme d'un tableau de groupes.

            Titres :
            {$titlesList}
            PROMPT;
    }

    /**
     * Parse une réponse JSON potentiellement enveloppée dans un bloc markdown.
     *
     * @return array<mixed>|null
     */
    private function parseJsonFromText(string $text): ?array
    {
        $cleaned = \preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', \trim($text));
        $data = \json_decode($cleaned ?? $text, true);

        if (!\is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Construit les MergeGroup à partir des données JSON parsées.
     *
     * @param array<mixed>            $data
     * @param array<int, ComicSeries> $seriesMap
     *
     * @return list<MergeGroup>
     */
    private function buildMergeGroups(array $data, array $seriesMap): array
    {
        $groups = [];

        foreach ($data as $groupData) {
            if (!\is_array($groupData)) {
                continue;
            }

            /** @var array<string, mixed> $groupData */
            if (!isset($groupData['title']) || !\is_string($groupData['title'])) {
                continue;
            }

            if (!isset($groupData['entries']) || !\is_array($groupData['entries'])) {
                continue;
            }

            $entries = [];
            foreach ($groupData['entries'] as $entryData) {
                if (!\is_array($entryData)) {
                    continue;
                }

                /** @var array<string, mixed> $entryData */
                if (!isset($entryData['id']) || !\is_numeric($entryData['id'])) {
                    continue;
                }

                $seriesId = \intval($entryData['id']);
                $series = $seriesMap[$seriesId] ?? null;
                if (null === $series) {
                    continue;
                }

                $tomeNumber = $entryData['tomeNumber'] ?? null;

                $entries[] = new MergeGroupEntry(
                    originalTitle: $series->getTitle(),
                    seriesId: $seriesId,
                    suggestedTomeNumber: \is_numeric($tomeNumber) ? \intval($tomeNumber) : null,
                );
            }

            // Filtrer les groupes avec moins de 2 entrées
            if (\count($entries) < 2) {
                continue;
            }

            $groups[] = new MergeGroup(
                entries: $entries,
                suggestedTitle: $groupData['title'],
            );
        }

        return $groups;
    }
}
