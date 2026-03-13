<?php

declare(strict_types=1);

namespace App\Service\Lookup;

/**
 * Utilitaire pour optimiser les URLs de couvertures Google Books.
 *
 * Transforme les URLs pour obtenir une meilleure qualité d'image :
 * HTTPS, zoom=0 (plus grande résolution), suppression du curl de page.
 */
final class GoogleBooksUrlHelper
{
    public static function optimizeThumbnailUrl(string $url): string
    {
        if (!\str_contains($url, 'books.google.com/')) {
            return $url;
        }

        $url = (string) \preg_replace('#^http://#', 'https://', $url);
        $url = \str_replace('zoom=1', 'zoom=0', $url);
        $url = (string) \preg_replace('/&?edge=curl&?/', '&', $url);

        return \rtrim($url, '&');
    }
}
