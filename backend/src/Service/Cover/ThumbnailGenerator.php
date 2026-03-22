<?php

declare(strict_types=1);

namespace App\Service\Cover;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Psr\Log\LoggerInterface;

/**
 * Génère les miniatures de couverture via LiipImagine.
 *
 * Pré-génère le cache pour que les miniatures soient servies
 * directement par nginx en production (pas de fallback PHP).
 */
readonly class ThumbnailGenerator
{
    private const string FILTER = 'cover_thumbnail';
    private const string URI_PREFIX = '/uploads/covers/';

    public function __construct(
        private CacheManager $cacheManager,
        private DataManager $dataManager,
        private FilterManager $filterManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Génère la miniature pour une image de couverture.
     */
    public function generate(string $coverImage): void
    {
        $path = self::URI_PREFIX.$coverImage;

        if ($this->cacheManager->isStored($path, self::FILTER)) {
            return;
        }

        try {
            $binary = $this->dataManager->find(self::FILTER, $path);
            $filteredBinary = $this->filterManager->applyFilter($binary, self::FILTER);
            $this->cacheManager->store($filteredBinary, $path, self::FILTER);
        } catch (\Throwable $e) {
            $this->logger->warning('Échec de la génération de miniature : {message}', [
                'coverImage' => $coverImage,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
