<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cover;

use App\Entity\ComicSeries;
use App\Service\Cover\Upload\UploadHandlerInterface;
use App\Service\Cover\VichCoverRemover;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

/**
 * Tests unitaires pour VichCoverRemover.
 */
final class VichCoverRemoverTest extends TestCase
{
    private CacheManager&MockObject $cacheManager;
    private VichCoverRemover $remover;
    private UploadHandlerInterface&MockObject $uploadHandler;
    private UploaderHelperInterface&MockObject $uploaderHelper;

    protected function setUp(): void
    {
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->uploadHandler = $this->createMock(UploadHandlerInterface::class);
        $this->uploaderHelper = $this->createMock(UploaderHelperInterface::class);

        $this->remover = new VichCoverRemover(
            $this->cacheManager,
            $this->uploadHandler,
            $this->uploaderHelper,
        );
    }

    /**
     * Teste qu'avec une coverImage et un chemin valide, le cache est invalid\u00e9 et le fichier supprim\u00e9.
     */
    public function testRemoveWithCoverImageAndPathInvalidatesCacheAndRemovesFile(): void
    {
        $entity = new ComicSeries();
        $entity->setCoverImage('cover.jpg');

        $this->uploaderHelper
            ->expects(self::once())
            ->method('asset')
            ->with($entity, 'coverFile')
            ->willReturn('/uploads/covers/cover.jpg');

        $this->cacheManager
            ->expects(self::once())
            ->method('remove')
            ->with('/uploads/covers/cover.jpg');

        $this->uploadHandler
            ->expects(self::once())
            ->method('remove')
            ->with($entity, 'coverFile');

        $this->remover->remove($entity);
    }

    /**
     * Teste qu'avec une coverImage mais un chemin null, le cache n'est pas invalid\u00e9 mais le fichier est supprim\u00e9.
     */
    public function testRemoveWithCoverImageAndNullPathSkipsCacheButRemovesFile(): void
    {
        $entity = new ComicSeries();
        $entity->setCoverImage('cover.jpg');

        $this->uploaderHelper
            ->expects(self::once())
            ->method('asset')
            ->with($entity, 'coverFile')
            ->willReturn(null);

        $this->cacheManager
            ->expects(self::never())
            ->method('remove');

        $this->uploadHandler
            ->expects(self::once())
            ->method('remove')
            ->with($entity, 'coverFile');

        $this->remover->remove($entity);
    }

    /**
     * Teste que sans coverImage, le cache n'est pas touch\u00e9 mais le handler de suppression est appel\u00e9.
     */
    public function testRemoveWithNoCoverImageSkipsCacheButCallsUploadHandler(): void
    {
        $entity = new ComicSeries();
        // coverImage est null par d\u00e9faut

        $this->uploaderHelper
            ->expects(self::never())
            ->method('asset');

        $this->cacheManager
            ->expects(self::never())
            ->method('remove');

        $this->uploadHandler
            ->expects(self::once())
            ->method('remove')
            ->with($entity, 'coverFile');

        $this->remover->remove($entity);
    }
}
