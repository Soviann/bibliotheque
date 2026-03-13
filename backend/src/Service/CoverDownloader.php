<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ComicSeries;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

/**
 * Télécharge une image de couverture, la redimensionne en WebP et l'associe à une série.
 */
readonly class CoverDownloader
{
    private const int MAX_HEIGHT = 900;
    private const int MAX_WIDTH = 600;
    private const int WEBP_QUALITY = 85;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private UploadHandlerInterface $uploadHandler,
    ) {
    }

    /**
     * Télécharge l'image depuis l'URL, la convertit en WebP redimensionné et l'affecte à la série.
     */
    public function downloadAndStore(ComicSeries $series, string $url): bool
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);

            if (200 !== $response->getStatusCode()) {
                $this->logger->warning('Échec du téléchargement de la couverture : HTTP {code}', [
                    'code' => $response->getStatusCode(),
                    'series' => $series->getTitle(),
                    'url' => $url,
                ]);

                return false;
            }

            $content = $response->getContent();

            if ('' === $content) {
                $this->logger->warning('Couverture vide reçue', [
                    'series' => $series->getTitle(),
                    'url' => $url,
                ]);

                return false;
            }

            $source = @\imagecreatefromstring($content);

            if (false === $source) {
                $this->logger->warning('Image invalide reçue', [
                    'series' => $series->getTitle(),
                    'url' => $url,
                ]);

                return false;
            }

            $resized = $this->resize($source);
            $output = $this->ensureTrueColor($resized);
            $tempPath = \sprintf('%s/cover_%s_%s.webp', \sys_get_temp_dir(), $series->getId() ?? 0, \uniqid());

            \imagewebp($output, $tempPath, self::WEBP_QUALITY);

            if ($output !== $resized) {
                \imagedestroy($output);
            }
            if ($resized !== $source) {
                \imagedestroy($resized);
            }
            \imagedestroy($source);

            $series->setCoverFile(new ReplacingFile($tempPath));
            $this->uploadHandler->upload($series, 'coverFile');

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur lors du téléchargement de la couverture : {message}', [
                'message' => $e->getMessage(),
                'series' => $series->getTitle(),
                'url' => $url,
            ]);

            return false;
        }
    }

    /**
     * Convertit une image palette (GIF, PNG 8-bit) en truecolor pour compatibilité WebP.
     */
    private function ensureTrueColor(\GdImage $image): \GdImage
    {
        if (\imageistruecolor($image)) {
            return $image;
        }

        $width = \imagesx($image);
        $height = \imagesy($image);
        $trueColor = \imagecreatetruecolor($width, $height);
        \assert(false !== $trueColor);
        \imagecopy($trueColor, $image, 0, 0, 0, 0, $width, $height);

        return $trueColor;
    }

    /**
     * Redimensionne l'image pour respecter les dimensions maximales sans upscale.
     *
     * @param \GdImage $source Image source
     *
     * @return \GdImage Image redimensionnée (ou la source si pas de redimensionnement)
     */
    private function resize(\GdImage $source): \GdImage
    {
        $originalHeight = \imagesy($source);
        $originalWidth = \imagesx($source);

        if ($originalWidth <= self::MAX_WIDTH && $originalHeight <= self::MAX_HEIGHT) {
            return $source;
        }

        $ratio = \min(self::MAX_WIDTH / $originalWidth, self::MAX_HEIGHT / $originalHeight);
        /** @var int<1, max> $newHeight */
        $newHeight = \max(1, (int) \round($originalHeight * $ratio));
        /** @var int<1, max> $newWidth */
        $newWidth = \max(1, (int) \round($originalWidth * $ratio));

        $resized = \imagecreatetruecolor($newWidth, $newHeight);
        \assert(false !== $resized);
        \imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        return $resized;
    }
}
