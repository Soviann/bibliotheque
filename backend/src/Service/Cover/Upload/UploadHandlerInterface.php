<?php

declare(strict_types=1);

namespace App\Service\Cover\Upload;

/**
 * Interface pour le handler de suppression de fichiers uploadés.
 *
 * Abstrait la dépendance sur le UploadHandler final de VichUploader.
 */
interface UploadHandlerInterface
{
    public function remove(object $obj, string $fieldName): void;

    public function upload(object $obj, string $fieldName): void;
}
