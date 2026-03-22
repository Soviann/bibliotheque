<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Recommendation;

use App\DTO\AuthorReleaseResult;
use App\Enum\ComicType;
use App\Repository\AuthorRepository;
use App\Repository\ComicSeriesRepository;
use App\Repository\UserRepository;
use App\Service\Lookup\Gemini\GeminiClientPool;
use App\Service\Lookup\Gemini\GeminiQueryService;
use App\Service\Notification\NotifierInterface;
use App\Service\Recommendation\AuthorReleaseCheckerService;
use App\Tests\Factory\EntityFactory;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests unitaires pour AuthorReleaseCheckerService.
 */
final class AuthorReleaseCheckerServiceTest extends TestCase
{
    private AuthorRepository&Stub $authorRepository;
    private ComicSeriesRepository&Stub $comicSeriesRepository;
    private GeminiClientPool&Stub $geminiClientPool;
    private UserRepository&Stub $userRepository;

    protected function setUp(): void
    {
        $this->authorRepository = $this->createStub(AuthorRepository::class);
        $this->comicSeriesRepository = $this->createStub(ComicSeriesRepository::class);
        $this->geminiClientPool = $this->createStub(GeminiClientPool::class);
        $this->userRepository = $this->createStub(UserRepository::class);
    }

    public function testCheckReturnsEmptyWhenNoFollowedAuthors(): void
    {
        $this->authorRepository->method('findFollowed')->willReturn([]);
        $this->userRepository->method('findOneBy')->willReturn(EntityFactory::createUser());

        $results = \iterator_to_array($this->buildService()->check());

        self::assertSame([], $results);
    }

    public function testCheckReturnsEmptyWhenNoUser(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $results = \iterator_to_array($this->buildService()->check());

        self::assertSame([], $results);
    }

    public function testCheckYieldsNewSeriesFromGemini(): void
    {
        $user = EntityFactory::createUser();
        $author = EntityFactory::createAuthor('Naoki Urasawa');
        $author->setFollowedForNewSeries(true);

        $series = EntityFactory::createComicSeries('Monster', type: ComicType::MANGA);
        $author->addComicSeries($series);

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->authorRepository->method('findFollowed')->willReturn([$author]);
        $this->comicSeriesRepository->method('findAllTitlesLower')->willReturn(['monster', '20th century boys']);
        $this->stubGeminiResponse('[{"title": "Pluto", "type": "manga"}]');

        $results = \iterator_to_array($this->buildService()->check(dryRun: true));

        self::assertCount(1, $results);
        self::assertInstanceOf(AuthorReleaseResult::class, $results[0]);
        self::assertSame('Naoki Urasawa', $results[0]->authorName);
        self::assertSame('Pluto', $results[0]->newSeriesTitle);
        self::assertSame(ComicType::MANGA, $results[0]->type);
    }

    public function testCheckSkipsSeriesAlreadyInLibrary(): void
    {
        $user = EntityFactory::createUser();
        $author = EntityFactory::createAuthor('Naoki Urasawa');
        $author->setFollowedForNewSeries(true);

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->authorRepository->method('findFollowed')->willReturn([$author]);
        $this->comicSeriesRepository->method('findAllTitlesLower')->willReturn(['pluto']);
        $this->stubGeminiResponse('[{"title": "Pluto", "type": "manga"}]');

        $results = \iterator_to_array($this->buildService()->check(dryRun: true));

        self::assertSame([], $results);
    }

    public function testCheckCreatesNotificationWhenNotDryRun(): void
    {
        $user = EntityFactory::createUser();
        $author = EntityFactory::createAuthor('Eiichiro Oda');
        $author->setFollowedForNewSeries(true);

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->authorRepository->method('findFollowed')->willReturn([$author]);
        $this->comicSeriesRepository->method('findAllTitlesLower')->willReturn([]);
        $this->stubGeminiResponse('[{"title": "Wanted!", "type": "manga"}]');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('create');

        \iterator_to_array($this->buildService($notifier)->check(dryRun: false));
    }

    public function testCheckDoesNotNotifyInDryRun(): void
    {
        $user = EntityFactory::createUser();
        $author = EntityFactory::createAuthor('Eiichiro Oda');
        $author->setFollowedForNewSeries(true);

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->authorRepository->method('findFollowed')->willReturn([$author]);
        $this->comicSeriesRepository->method('findAllTitlesLower')->willReturn([]);
        $this->stubGeminiResponse('[{"title": "Wanted!", "type": "manga"}]');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::never())->method('create');

        \iterator_to_array($this->buildService($notifier)->check(dryRun: true));
    }

    public function testCheckSkipsInvalidGeminiResults(): void
    {
        $user = EntityFactory::createUser();
        $author = EntityFactory::createAuthor('Test');
        $author->setFollowedForNewSeries(true);

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->authorRepository->method('findFollowed')->willReturn([$author]);
        $this->comicSeriesRepository->method('findAllTitlesLower')->willReturn([]);
        $this->stubGeminiResponse('[{"title": null, "type": "manga"}, {"type": "bd"}, {"title": "Valid", "type": "bd"}]');

        $results = \iterator_to_array($this->buildService()->check(dryRun: true));

        self::assertCount(1, $results);
        self::assertSame('Valid', $results[0]->newSeriesTitle);
    }

    public function testCheckContinuesOnAuthorError(): void
    {
        $user = EntityFactory::createUser();
        $author1 = EntityFactory::createAuthor('Error Author');
        $author1->setFollowedForNewSeries(true);
        $author2 = EntityFactory::createAuthor('Good Author');
        $author2->setFollowedForNewSeries(true);

        $this->userRepository->method('findOneBy')->willReturn($user);
        $this->authorRepository->method('findFollowed')->willReturn([$author1, $author2]);
        $this->comicSeriesRepository->method('findAllTitlesLower')->willReturn([]);

        $callCount = 0;
        $this->geminiClientPool->method('executeWithRetry')
            ->willReturnCallback(static function () use (&$callCount): string {
                ++$callCount;
                if (1 === $callCount) {
                    throw new \RuntimeException('API error');
                }

                return '[{"title": "New Series", "type": "bd"}]';
            });

        $results = \iterator_to_array($this->buildService()->check(dryRun: true));

        self::assertCount(1, $results);
        self::assertSame('Good Author', $results[0]->authorName);
    }

    private function buildService(?NotifierInterface $notifier = null): AuthorReleaseCheckerService
    {
        return new AuthorReleaseCheckerService(
            $this->authorRepository,
            $this->comicSeriesRepository,
            new GeminiQueryService($this->geminiClientPool),
            new NullLogger(),
            $notifier ?? $this->createStub(NotifierInterface::class),
            $this->userRepository,
        );
    }

    /**
     * Configure le stub GeminiClientPool pour retourner une réponse JSON.
     */
    private function stubGeminiResponse(string $json): void
    {
        $this->geminiClientPool->method('executeWithRetry')->willReturn($json);
    }
}
