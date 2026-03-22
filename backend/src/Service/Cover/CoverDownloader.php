<?php

declare(strict_types=1);

namespace App\Service\Cover;

use App\Entity\ComicSeries;
use App\Service\Cover\Upload\UploadHandlerInterface;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
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
        private ImageManager $imageManager,
        private LoggerInterface $logger,
        private ThumbnailGenerator $thumbnailGenerator,
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

            $tempPath = \sprintf('%s/cover_%s_%s.webp', \sys_get_temp_dir(), $series->getId() ?? 0, \uniqid());

            $this->imageManager->read($content)
                ->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT)
                ->encode(new WebpEncoder(self::WEBP_QUALITY))
                ->save($tempPath);

            $series->setCoverFile(new ReplacingFile($tempPath));
            $this->uploadHandler->upload($series, 'coverFile');

            if (null !== $series->getCoverImage()) {
                $this->thumbnailGenerator->generate($series->getCoverImage());
            }

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
}
