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
    private const int MIN_HEIGHT = 180;
    private const int MIN_WIDTH = 120;
    private const float MIN_ASPECT_RATIO = 0.40;
    private const float MAX_ASPECT_RATIO = 0.95;
    private const int MIN_PAYLOAD_SIZE = 100;
    private const int WEBP_QUALITY = 85;

    /** Motifs d'URL indiquant des images de remplacement ou d'absence de couverture. */
    private const array PLACEHOLDER_URL_PATTERNS = [
        'blank.gif',
        'default.jpg',
        'default.png',
        'gbs_preview_button',
        'image-not-available',
        'no-cover',
        'no_cover',
        'nocover',
        'pixel.gif',
        'placeholder',
        'spacer.gif',
    ];

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

        if ($this->isPlaceholderUrl($url)) {
            $this->logger->warning('Téléchargement de couverture rejeté : URL de placeholder détectée', [
                'series' => $series->getTitle(),
                'url' => $url,
            ]);

            return false;
        }

        $currentUrl = $url;
        $maxRedirects = 3;
        $redirectCount = 0;

        try {
            $response = null;

            while ($redirectCount <= $maxRedirects) {
                $response = $this->httpClient->request('GET', $currentUrl, [
                    'max_redirects' => 0,
                    'timeout' => 15,
                ]);

                $statusCode = $response->getStatusCode();

                if (\in_array($statusCode, [301, 302, 307, 308], true)) {
                    $headers = $response->getHeaders(false);
                    $location = $headers['location'][0] ?? null;

                    if (null === $location || '' === $location) {
                        $this->logger->warning('Redirection sans en-tête Location pour {url}', [
                            'series' => $series->getTitle(),
                            'url' => $currentUrl,
                        ]);

                        return false;
                    }

                    $nextUrl = $this->resolveRedirectUrl($currentUrl, $location);

                    if (!$this->isSafeUrl($nextUrl) || $this->isPlaceholderUrl($nextUrl)) {
                        $this->logger->warning('Redirection de couverture rejetée pour URL non sécurisée ou placeholder : {nextUrl}', [
                            'nextUrl' => $nextUrl,
                            'series' => $series->getTitle(),
                            'url' => $currentUrl,
                        ]);

                        return false;
                    }

                    $currentUrl = $nextUrl;
                    ++$redirectCount;

                    continue;
                }

                break;
            }

            if (200 !== $response->getStatusCode()) {
                $this->logger->warning('Échec du téléchargement de la couverture : HTTP {code}', [
                    'code' => $response->getStatusCode(),
                    'series' => $series->getTitle(),
                    'url' => $currentUrl,
                ]);

                return false;
            }

            $content = $response->getContent();

            if (\strlen($content) < self::MIN_PAYLOAD_SIZE) {
                $this->logger->warning('Couverture rejetée : fichier trop petit ou vide (< {min} octets)', [
                    'min' => self::MIN_PAYLOAD_SIZE,
                    'series' => $series->getTitle(),
                    'size' => \strlen($content),
                    'url' => $url,
                ]);

                return false;
            }

            $image = $this->imageManager->decode($content);
            $width = $image->width();
            $height = $image->height();

            if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
                $this->logger->warning('Couverture rejetée : résolution insuffisante ({width}x{height}, minimum {minWidth}x{minHeight})', [
                    'height' => $height,
                    'minHeight' => self::MIN_HEIGHT,
                    'minWidth' => self::MIN_WIDTH,
                    'series' => $series->getTitle(),
                    'url' => $url,
                    'width' => $width,
                ]);

                return false;
            }

            $ratio = $width / $height;
            if ($ratio < self::MIN_ASPECT_RATIO || $ratio > self::MAX_ASPECT_RATIO) {
                $this->logger->warning('Couverture rejetée : ratio d\'aspect inadapté pour une couverture ({ratio}, attendu entre {minRatio} et {maxRatio})', [
                    'height' => $height,
                    'maxRatio' => self::MAX_ASPECT_RATIO,
                    'minRatio' => self::MIN_ASPECT_RATIO,
                    'ratio' => \round($ratio, 2),
                    'series' => $series->getTitle(),
                    'url' => $url,
                    'width' => $width,
                ]);

                return false;
            }

            $tempPath = \sprintf('%s/cover_%s_%s.webp', \sys_get_temp_dir(), $series->getId() ?? 0, \uniqid());

            $image->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT)
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
     * Détecte si l'URL correspond à un placeholder connu.
     */
    private function isPlaceholderUrl(string $url): bool
    {
        $lower = \mb_strtolower($url);
        foreach (self::PLACEHOLDER_URL_PATTERNS as $pattern) {
            if (\str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
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

    /**
     * Résout une URL de redirection (relative ou absolue) par rapport à l'URL courante.
     */
    private function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        if (1 === \preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parsed = \parse_url($baseUrl);
        if (false === $parsed || !isset($parsed['scheme'], $parsed['host'])) {
            return $location;
        }

        $scheme = $parsed['scheme'];
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        if (\str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        if (\str_starts_with($location, '/')) {
            return \sprintf('%s://%s%s%s', $scheme, $host, $port, $location);
        }

        $path = $parsed['path'] ?? '/';
        $dir = \dirname($path);
        $cleanDir = '/' === $dir ? '' : $dir;

        return \sprintf('%s://%s%s%s/%s', $scheme, $host, $port, $cleanDir, $location);
    }
}
