<?php

declare(strict_types=1);

namespace App\Service;

use Vich\UploaderBundle\Handler\UploadHandler;

/**
 * Adaptateur pour le UploadHandler final de VichUploader.
 */
final readonly class VichUploadHandlerAdapter implements UploadHandlerInterface
{
    public function __construct(
        private UploadHandler $uploadHandler,
    ) {
    }

    public function remove(object $obj, string $fieldName): void
    {
        $this->uploadHandler->remove($obj, $fieldName);
    }
}
