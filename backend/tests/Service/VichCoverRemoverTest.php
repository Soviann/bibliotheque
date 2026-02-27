<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ComicSeries;
use App\Service\UploadHandlerInterface;
use App\Service\VichCoverRemover;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

/**
 * Tests pour VichCoverRemover.
 */
class VichCoverRemoverTest extends TestCase
{
    /**
     * Teste que uploadHandler->remove() et cacheManager->remove() sont appelés.
     */
    public function testRemoveCallsUploadHandlerAndInvalidatesCache(): void
    {
        $comic = $this->createComic('cover.jpg');

        $uploaderHelper = $this->createMock(UploaderHelperInterface::class);
        $uploaderHelper->expects(self::once())
            ->method('asset')
            ->with($comic, 'coverFile')
            ->willReturn('/uploads/covers/cover.jpg');

        $uploadHandler = $this->createMock(UploadHandlerInterface::class);
        $uploadHandler->expects(self::once())
            ->method('remove')
            ->with($comic, 'coverFile');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::once())
            ->method('remove')
            ->with('/uploads/covers/cover.jpg');

        $remover = new VichCoverRemover($cacheManager, $uploadHandler, $uploaderHelper);
        $remover->remove($comic);
    }

    /**
     * Teste que si coverImage est null, cacheManager->remove() n'est PAS appelé.
     */
    public function testRemoveDoesNotInvalidateCacheWhenNoCoverImage(): void
    {
        $comic = $this->createComic(null);

        $uploadHandler = $this->createMock(UploadHandlerInterface::class);
        $uploadHandler->expects(self::once())
            ->method('remove')
            ->with($comic, 'coverFile');

        $uploaderHelper = $this->createMock(UploaderHelperInterface::class);
        $uploaderHelper->expects(self::never())
            ->method('asset');

        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::never())
            ->method('remove');

        $remover = new VichCoverRemover($cacheManager, $uploadHandler, $uploaderHelper);
        $remover->remove($comic);
    }

    private function createComic(?string $coverImage): ComicSeries
    {
        $comic = new ComicSeries();

        if (null !== $coverImage) {
            $reflection = new \ReflectionProperty(ComicSeries::class, 'coverImage');
            $reflection->setValue($comic, $coverImage);
        }

        return $comic;
    }
}
