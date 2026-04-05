<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cover;

use App\Service\Cover\ThumbnailGenerator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Model\Binary;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests unitaires pour ThumbnailGenerator.
 */
final class ThumbnailGeneratorTest extends TestCase
{
    private CacheManager&MockObject $cacheManager;
    private DataManager&MockObject $dataManager;
    private FilterManager&MockObject $filterManager;
    private ThumbnailGenerator $generator;

    protected function setUp(): void
    {
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->dataManager = $this->createMock(DataManager::class);
        $this->filterManager = $this->createMock(FilterManager::class);

        $this->generator = new ThumbnailGenerator(
            $this->cacheManager,
            $this->dataManager,
            $this->filterManager,
            new NullLogger(),
        );
    }

    public function testGenerateInvalidatesCacheThenRegenerates(): void
    {
        $path = '/uploads/covers/abc123.webp';
        $binary = new Binary('image-data', 'image/webp', 'webp');
        $filteredBinary = new Binary('thumbnail-data', 'image/webp', 'webp');

        $this->cacheManager->expects(self::once())
            ->method('remove')
            ->with($path);

        $this->dataManager->expects(self::once())
            ->method('find')
            ->with('cover_thumbnail', $path)
            ->willReturn($binary);

        $this->filterManager->expects(self::once())
            ->method('applyFilter')
            ->with($binary, 'cover_thumbnail')
            ->willReturn($filteredBinary);

        $this->cacheManager->expects(self::once())
            ->method('store')
            ->with($filteredBinary, $path, 'cover_thumbnail');

        $this->generator->generate('abc123.webp');
    }

    public function testGenerateLogsErrorOnFailure(): void
    {
        $this->cacheManager->method('remove');

        $this->cacheManager->expects(self::never())->method('store');

        $this->dataManager->method('find')
            ->willThrowException(new \RuntimeException('File not found'));

        // Ne doit pas lancer d'exception
        $this->generator->generate('broken.webp');
    }
}
