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

    /**
     * @param (callable(string): list<string>)|null $dnsResolver
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private ImageManager $imageManager,
        private LoggerInterface $logger,
        private ThumbnailGenerator $thumbnailGenerator,
        private UploadHandlerInterface $uploadHandler,
        private mixed $dnsResolver = null,
    ) {
    }

    /**
     * Télécharge l'image depuis l'URL, la convertit en WebP redimensionné et l'affecte à la série.
     */
    public function downloadAndStore(ComicSeries $series, string $url): bool
    {
        if (!$this->isSafeUrl($url)) {
            $this->logger->warning('Téléchargement de couverture rejeté pour URL non sécurisée (SSRF)', [
                'series' => $series->getTitle(),
                'url' => $url,
            ]);

            return false;
        }
        try {
            $response = $this->httpClient->request('GET', $url, [
                'max_redirects' => 0,
                'timeout' => 15,
            ]);

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

            $this->imageManager->decode($content)
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

    /**
     * Valide qu'une URL est sécurisée contre les attaques SSRF.
     */
    private function isSafeUrl(string $url): bool
    {
        $parsed = \parse_url($url);
        if (false === $parsed || !isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        $scheme = \mb_strtolower($parsed['scheme']);
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if (isset($parsed['port']) && !\in_array($parsed['port'], [80, 443], true)) {
            return false;
        }

        $host = \mb_strtolower($parsed['host']);
        if ('' === $host || 'localhost' === $host || \str_ends_with($host, '.localhost') || \str_ends_with($host, '.local') || \str_ends_with($host, '.internal') || \str_ends_with($host, '.lan')) {
            return false;
        }

        // Si l'hôte est une adresse IP directe (y compris IPv6 entre crochets)
        $rawHost = \trim($host, '[]');
        if (false !== \filter_var($rawHost, \FILTER_VALIDATE_IP)) {
            return false !== \filter_var($rawHost, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE);
        }

        // L'hôte est un nom de domaine : résolution DNS
        /** @var list<string>|false $ips */
        $ips = null !== $this->dnsResolver ? ($this->dnsResolver)($host) : @\gethostbynamel($host);
        if (false === $ips || [] === $ips) {
            return false;
        }

        foreach ($ips as $ip) {
            if (false === \filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
