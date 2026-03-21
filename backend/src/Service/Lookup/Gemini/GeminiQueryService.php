<?php

declare(strict_types=1);

namespace App\Service\Lookup\Gemini;

/**
 * Exécute une requête Gemini et parse la réponse JSON.
 */
final readonly class GeminiQueryService
{
    public function __construct(
        private GeminiClientPool $geminiClientPool,
    ) {
    }

    /**
     * Envoie un prompt à Gemini et retourne le tableau JSON parsé.
     *
     * @return list<array<string, mixed>>
     */
    public function queryJsonArray(string $prompt): array
    {
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

        /** @var list<array<string, mixed>> $result */
        $result = $parsed;

        return $result;
    }
}
