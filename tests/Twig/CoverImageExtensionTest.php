<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\ComicSeries;
use App\Twig\CoverImageExtension;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

/**
 * Tests pour CoverImageExtension.
 */
class CoverImageExtensionTest extends TestCase
{
    /**
     * Teste qu'une cover uploadée renvoie l'URL filtrée via CacheManager.
     */
    public function testUploadedCoverReturnsCacheManagerUrl(): void
    {
        $comic = $this->createComic(coverImage: 'cover.jpg');

        $uploaderHelper = $this->createMock(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')
            ->with($comic, 'coverFile')
            ->willReturn('/uploads/covers/cover.jpg');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getBrowserPath')
            ->with('/uploads/covers/cover.jpg', 'cover_thumbnail')
            ->willReturn('/media/cache/cover_thumbnail/uploads/covers/cover.jpg');

        $extension = new CoverImageExtension($cacheManager, $uploaderHelper);

        self::assertSame(
            '/media/cache/cover_thumbnail/uploads/covers/cover.jpg',
            $extension->coverImageUrl($comic),
        );
    }

    /**
     * Teste qu'une cover URL externe est renvoyée telle quelle.
     */
    public function testExternalCoverUrlReturnedAsIs(): void
    {
        $comic = $this->createComic(coverUrl: 'https://example.com/cover.jpg');

        $extension = new CoverImageExtension(
            $this->createMock(CacheManager::class),
            $this->createMock(UploaderHelperInterface::class),
        );

        self::assertSame('https://example.com/cover.jpg', $extension->coverImageUrl($comic));
    }

    /**
     * Teste qu'aucune cover renvoie une chaîne vide.
     */
    public function testNoCoverReturnsEmptyString(): void
    {
        $comic = $this->createComic();

        $extension = new CoverImageExtension(
            $this->createMock(CacheManager::class),
            $this->createMock(UploaderHelperInterface::class),
        );

        self::assertSame('', $extension->coverImageUrl($comic));
    }

    /**
     * Teste que le filtre par défaut est cover_thumbnail.
     */
    public function testDefaultFilterIsCoverThumbnail(): void
    {
        $comic = $this->createComic(coverImage: 'cover.jpg');

        $uploaderHelper = $this->createMock(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturn('/uploads/covers/cover.jpg');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::once())
            ->method('getBrowserPath')
            ->with('/uploads/covers/cover.jpg', 'cover_thumbnail')
            ->willReturn('/media/cache/cover_thumbnail/uploads/covers/cover.jpg');

        $extension = new CoverImageExtension($cacheManager, $uploaderHelper);
        $extension->coverImageUrl($comic);
    }

    /**
     * Teste que le filtre cover_medium est accepté.
     */
    public function testCoverMediumFilterAccepted(): void
    {
        $comic = $this->createComic(coverImage: 'cover.jpg');

        $uploaderHelper = $this->createMock(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturn('/uploads/covers/cover.jpg');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::once())
            ->method('getBrowserPath')
            ->with('/uploads/covers/cover.jpg', 'cover_medium')
            ->willReturn('/media/cache/cover_medium/uploads/covers/cover.jpg');

        $extension = new CoverImageExtension($cacheManager, $uploaderHelper);

        self::assertSame(
            '/media/cache/cover_medium/uploads/covers/cover.jpg',
            $extension->coverImageUrl($comic, 'cover_medium'),
        );
    }

    /**
     * Teste que coverImage a priorité sur coverUrl.
     */
    public function testUploadedCoverTakesPriorityOverUrl(): void
    {
        $comic = $this->createComic(coverImage: 'cover.jpg', coverUrl: 'https://example.com/cover.jpg');

        $uploaderHelper = $this->createMock(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturn('/uploads/covers/cover.jpg');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getBrowserPath')
            ->willReturn('/media/cache/cover_thumbnail/uploads/covers/cover.jpg');

        $extension = new CoverImageExtension($cacheManager, $uploaderHelper);

        self::assertSame(
            '/media/cache/cover_thumbnail/uploads/covers/cover.jpg',
            $extension->coverImageUrl($comic),
        );
    }

    private function createComic(?string $coverImage = null, ?string $coverUrl = null): ComicSeries
    {
        $comic = new ComicSeries();

        if (null !== $coverImage) {
            $reflection = new \ReflectionProperty(ComicSeries::class, 'coverImage');
            $reflection->setValue($comic, $coverImage);
        }

        if (null !== $coverUrl) {
            $reflection = new \ReflectionProperty(ComicSeries::class, 'coverUrl');
            $reflection->setValue($comic, $coverUrl);
        }

        return $comic;
    }
}
