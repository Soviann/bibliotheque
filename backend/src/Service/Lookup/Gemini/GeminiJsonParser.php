<?php

declare(strict_types=1);

namespace App\Service\Lookup\Gemini;

/**
 * Utilitaire pour parser les réponses JSON de Gemini.
 *
 * Gère les réponses encapsulées dans des blocs de code Markdown (```json ... ```).
 */
final class GeminiJsonParser
{
    /**
     * Parse du texte JSON potentiellement encapsulé dans un bloc de code Markdown.
     *
     * @return array<mixed>|null
     */
    public static function parseJsonFromText(string $text): ?array
    {
        $cleaned = \preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', \trim($text));

        $data = \json_decode($cleaned ?? $text, true);

        if (!\is_array($data)) {
            return null;
        }

        return $data; // @phpstan-ignore return.type
    }
}
