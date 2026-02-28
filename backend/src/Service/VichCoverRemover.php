<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ComicSeries;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

/**
 * Implémentation utilisant VichUploader pour supprimer les couvertures.
 *
 * Invalide le cache LiipImagine lors de la suppression.
 */
final readonly class VichCoverRemover implements CoverRemoverInterface
{
    public function __construct(
        private CacheManager $cacheManager,
        private UploadHandlerInterface $uploadHandler,
        private UploaderHelperInterface $uploaderHelper,
    ) {
    }

    public function remove(ComicSeries $entity): void
    {
        if (null !== $entity->getCoverImage()) {
            $path = $this->uploaderHelper->asset($entity, 'coverFile');

            if (null !== $path) {
                $this->cacheManager->remove($path);
            }
        }

        $this->uploadHandler->remove($entity, 'coverFile');
    }
}
