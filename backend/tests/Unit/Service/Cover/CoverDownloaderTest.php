<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cover;

use App\Entity\ComicSeries;
use App\Service\Cover\CoverDownloader;
use App\Service\Cover\ThumbnailGenerator;
use App\Service\Cover\Upload\UploadHandlerInterface;
use App\Tests\Factory\EntityFactory;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests unitaires pour CoverDownloader.
 */
final class CoverDownloaderTest extends TestCase
{
    private ThumbnailGenerator&Stub $thumbnailGenerator;
    private UploadHandlerInterface&Stub $uploadHandler;

    protected function setUp(): void
    {
        $this->thumbnailGenerator = $this->createStub(ThumbnailGenerator::class);
        $this->uploadHandler = $this->createStub(UploadHandlerInterface::class);
    }

    public function testDownloadAndStoreSetsCoverFile(): void
    {
        $imageData = $this->createTestImage(800, 1200);
        $httpClient = new MockHttpClient([new MockResponse($imageData, ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/cover.jpg');

        self::assertTrue($result);
        self::assertNotNull($series->getCoverFile());
        self::assertStringEndsWith('.webp', $series->getCoverFile()->getPathname());

        // Nettoyage
        @\unlink($series->getCoverFile()->getPathname());
    }

    public function testDownloadResizesLargeImage(): void
    {
        $imageData = $this->createTestImage(1200, 1800);
        $httpClient = new MockHttpClient([new MockResponse($imageData, ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $downloader->downloadAndStore($series, 'https://example.com/cover.jpg');

        $file = $series->getCoverFile();
        self::assertNotNull($file);

        $size = \getimagesize($file->getPathname());
        self::assertNotFalse($size);
        // Doit respecter 600x900 max
        self::assertLessThanOrEqual(600, $size[0]);
        self::assertLessThanOrEqual(900, $size[1]);

        @\unlink($file->getPathname());
    }

    public function testDownloadDoesNotUpscaleSmallImage(): void
    {
        $imageData = $this->createTestImage(200, 300);
        $httpClient = new MockHttpClient([new MockResponse($imageData, ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $downloader->downloadAndStore($series, 'https://example.com/small.jpg');

        $file = $series->getCoverFile();
        self::assertNotNull($file);

        $size = \getimagesize($file->getPathname());
        self::assertNotFalse($size);
        self::assertSame(200, $size[0]);
        self::assertSame(300, $size[1]);

        @\unlink($file->getPathname());
    }

    public function testDownloadReturnsFalseOnHttpError(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/missing.jpg');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadReturnsFalseOnInvalidImage(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('not an image', ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/bad.txt');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadReturnsFalseOnEmptyBody(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/empty');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadAndStoreGeneratesThumbnail(): void
    {
        $mockThumbnail = $this->createMock(ThumbnailGenerator::class);
        $imageData = $this->createTestImage(800, 1200);
        $httpClient = new MockHttpClient([new MockResponse($imageData, ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient, null, $mockThumbnail);

        // Simule le comportement de VichUploader qui définit coverImage après upload
        $this->uploadHandler->method('upload')
            ->willReturnCallback(static function (object $entity): void {
                \assert($entity instanceof ComicSeries);
                $entity->setCoverImage('cover_test.webp');
            });

        $mockThumbnail->expects(self::once())
            ->method('generate')
            ->with('cover_test.webp');

        $series = EntityFactory::createComicSeries('Test');
        $downloader->downloadAndStore($series, 'https://example.com/cover.jpg');

        // Nettoyage
        @\unlink($series->getCoverFile()?->getPathname() ?? '');
    }

    public function testDownloadDoesNotGenerateThumbnailOnFailure(): void
    {
        $mockThumbnail = $this->createMock(ThumbnailGenerator::class);
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);
        $downloader = $this->createDownloader($httpClient, null, $mockThumbnail);

        $mockThumbnail->expects(self::never())
            ->method('generate');

        $series = EntityFactory::createComicSeries('Test');
        $downloader->downloadAndStore($series, 'https://example.com/missing.jpg');
    }

    #[DataProvider('provideSsrfUrls')]
    public function testDownloadRejectsSsrfUrls(string $url): void
    {
        $httpClient = new MockHttpClient([new MockResponse('fake-image-data', ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, $url);

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSsrfUrls(): iterable
    {
        yield 'loopback ipv4' => ['http://127.0.0.1/secret.jpg'];
        yield 'private 10.x' => ['http://10.0.0.1/nas-admin.jpg'];
        yield 'private 192.168.x' => ['https://192.168.1.1/config.jpg'];
        yield 'private 172.16.x' => ['http://172.16.0.1/docker.jpg'];
        yield 'link-local cloud metadata' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'zero ip' => ['http://0.0.0.0/test.jpg'];
        yield 'localhost name' => ['http://localhost/cover.jpg'];
        yield 'subdomain localhost' => ['http://admin.localhost/cover.jpg'];
        yield 'local domain' => ['http://nas.local/cover.jpg'];
        yield 'internal domain' => ['http://db.internal/cover.jpg'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'ftp scheme' => ['ftp://example.com/cover.jpg'];
        yield 'gopher scheme' => ['gopher://example.com/'];
        yield 'disallowed port' => ['http://example.com:22/cover.jpg'];
        yield 'disallowed port 8080' => ['http://example.com:8080/cover.jpg'];
        yield 'loopback ipv6 in brackets' => ['http://[::1]/cover.jpg'];
        yield 'empty url' => [''];
        yield 'invalid url' => ['not-a-url'];
    }

    public function testDownloadRejectsDomainResolvingToPrivateIp(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('fake-image-data', ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient, static fn (string $_host): array => ['10.0.0.42']);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://malicious-domain.com/cover.jpg');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadRejectsLowResolutionImage(): void
    {
        $imageData = $this->createTestImage(80, 100);
        $httpClient = new MockHttpClient([new MockResponse($imageData, ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/too-small.jpg');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadRejectsLandscapeRatio(): void
    {
        $imageData = $this->createTestImage(800, 600); // ratio 1.33 > 0.95
        $httpClient = new MockHttpClient([new MockResponse($imageData, ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/landscape.jpg');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadRejectsTooNarrowRatio(): void
    {
        $imageData = $this->createTestImage(150, 600); // ratio 0.25 < 0.40
        $httpClient = new MockHttpClient([new MockResponse($imageData, ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/strip.jpg');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadRejectsPlaceholderUrl(): void
    {
        $httpClient = new MockHttpClient([]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/images/default.jpg');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadRejectsTinyPayload(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('GIF89a...', ['http_code' => 200])]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/tiny.gif');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadFollowsSafeRedirect(): void
    {
        $imageData = $this->createTestImage(600, 900);
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://example.com/cdn/cover.jpg']]),
            new MockResponse($imageData, ['http_code' => 200]),
        ]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/redirect-cover');

        self::assertTrue($result);
        self::assertNotNull($series->getCoverFile());

        @\unlink($series->getCoverFile()->getPathname());
    }

    public function testDownloadRejectsRedirectToPrivateIp(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'http://10.0.0.1/private.jpg']]),
        ]);
        $downloader = $this->createDownloader($httpClient);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/redirect-to-private');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    private function createDownloader(
        MockHttpClient $httpClient,
        ?callable $dnsResolver = null,
        ?ThumbnailGenerator $thumbnailGenerator = null,
    ): CoverDownloader {
        $resolver = $dnsResolver ?? static fn (string $host): array => match ($host) {
            'example.com' => ['93.184.216.34'],
            default => [],
        };

        return new CoverDownloader(
            $httpClient,
            new ImageManager(GdDriver::class),
            new NullLogger(),
            $thumbnailGenerator ?? $this->thumbnailGenerator,
            $this->uploadHandler,
            $resolver,
        );
    }

    private function createTestImage(int $width, int $height): string
    {
        \assert($width > 0 && $height > 0);
        $image = \imagecreatetruecolor($width, $height);
        \assert(false !== $image);
        \ob_start();
        \imagepng($image);
        \imagedestroy($image);

        return (string) \ob_get_clean();
    }
}
