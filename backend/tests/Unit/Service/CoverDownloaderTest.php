<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CoverDownloader;
use App\Service\UploadHandlerInterface;
use App\Tests\Factory\EntityFactory;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests unitaires pour CoverDownloader.
 */
final class CoverDownloaderTest extends TestCase
{
    private UploadHandlerInterface&MockObject $uploadHandler;

    protected function setUp(): void
    {
        $this->uploadHandler = $this->createMock(UploadHandlerInterface::class);
    }

    public function testDownloadAndStoreSetsCoverFile(): void
    {
        $imageData = $this->createTestImage(800, 1200);
        $httpClient = new MockHttpClient([new MockResponse($imageData, ['http_code' => 200])]);
        $downloader = new CoverDownloader($httpClient, ImageManager::gd(), new NullLogger(), $this->uploadHandler);

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
        $downloader = new CoverDownloader($httpClient, ImageManager::gd(), new NullLogger(), $this->uploadHandler);

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
        $downloader = new CoverDownloader($httpClient, ImageManager::gd(), new NullLogger(), $this->uploadHandler);

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
        $downloader = new CoverDownloader($httpClient, ImageManager::gd(), new NullLogger(), $this->uploadHandler);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/missing.jpg');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadReturnsFalseOnInvalidImage(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('not an image', ['http_code' => 200])]);
        $downloader = new CoverDownloader($httpClient, ImageManager::gd(), new NullLogger(), $this->uploadHandler);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/bad.txt');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
    }

    public function testDownloadReturnsFalseOnEmptyBody(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 200])]);
        $downloader = new CoverDownloader($httpClient, ImageManager::gd(), new NullLogger(), $this->uploadHandler);

        $series = EntityFactory::createComicSeries('Test');
        $result = $downloader->downloadAndStore($series, 'https://example.com/empty');

        self::assertFalse($result);
        self::assertNull($series->getCoverFile());
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
