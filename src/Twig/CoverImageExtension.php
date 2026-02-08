<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\ComicSeries;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

/**
 * Extension Twig pour générer les URLs des couvertures optimisées.
 */
class CoverImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly UploaderHelperInterface $uploaderHelper,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cover_image_url', $this->coverImageUrl(...)),
        ];
    }

    /**
     * Retourne l'URL optimisée de la couverture.
     *
     * - Cover uploadée : URL filtrée via LiipImagine (WebP, redimensionnée)
     * - Cover URL externe : URL renvoyée telle quelle
     * - Aucune cover : chaîne vide
     */
    public function coverImageUrl(ComicSeries $comic, string $filter = 'cover_thumbnail'): string
    {
        if (null !== $comic->getCoverImage()) {
            $path = $this->uploaderHelper->asset($comic, 'coverFile');

            return null !== $path ? $this->cacheManager->getBrowserPath($path, $filter) : '';
        }

        if (null !== $comic->getCoverUrl()) {
            return $comic->getCoverUrl();
        }

        return '';
    }
}
