<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ComicSeries;
use Vich\UploaderBundle\Handler\UploadHandler;

/**
 * Implémentation utilisant VichUploader pour supprimer les couvertures.
 */
final class VichCoverRemover implements CoverRemoverInterface
{
    public function __construct(
        private readonly UploadHandler $uploadHandler,
    ) {
    }

    public function remove(ComicSeries $entity): void
    {
        $this->uploadHandler->remove($entity, 'coverFile');
    }
}
