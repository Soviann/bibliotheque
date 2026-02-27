<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Input\AuthorInput;
use App\Dto\Input\ComicSeriesInput;
use App\Dto\Input\TomeInput;
use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\AuthorRepository;
use App\Service\ComicSeriesMapper;
use App\Service\CoverRemoverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * Tests unitaires pour ComicSeriesMapper.
 */
#[CoversClass(ComicSeriesMapper::class)]
class ComicSeriesMapperTest extends TestCase
{
    private ComicSeriesMapper $mapper;
    private AuthorRepository&MockObject $authorRepository;
    private CoverRemoverInterface&MockObject $coverRemover;
    private ObjectMapperInterface&MockObject $objectMapper;

    protected function setUp(): void
    {
        $this->authorRepository = $this->createMock(AuthorRepository::class);
        $this->coverRemover = $this->createMock(CoverRemoverInterface::class);
        $this->objectMapper = $this->createMock(ObjectMapperInterface::class);
        $this->mapper = new ComicSeriesMapper($this->authorRepository, $this->coverRemover, $this->objectMapper);
    }

    /**
     * Teste le mapping DTO → Entity pour une nouvelle série.
     */
    public function testMapToEntityCreatesNewSeries(): void
    {
        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->status = ComicStatus::BUYING;
        $input->type = ComicType::MANGA;
        $input->latestPublishedIssue = 10;
        $input->latestPublishedIssueComplete = false;
        $input->isOneShot = false;
        $input->description = 'A test description';
        $input->publishedDate = '2024-01-15';
        $input->publisher = 'Test Publisher';
        $input->coverUrl = 'https://example.com/cover.jpg';

        $entity = $this->mapper->mapToEntity($input);

        self::assertSame('Test Series', $entity->getTitle());
        self::assertSame(ComicStatus::BUYING, $entity->getStatus());
        self::assertSame(ComicType::MANGA, $entity->getType());
        self::assertSame(10, $entity->getLatestPublishedIssue());
        self::assertFalse($entity->isLatestPublishedIssueComplete());
        self::assertFalse($entity->isOneShot());
        self::assertFalse($entity->isWishlist());
        self::assertSame('A test description', $entity->getDescription());
        self::assertSame('2024-01-15', $entity->getPublishedDate());
        self::assertSame('Test Publisher', $entity->getPublisher());
        self::assertSame('https://example.com/cover.jpg', $entity->getCoverUrl());
    }

    /**
     * Teste le mapping DTO → Entity pour une série existante.
     */
    public function testMapToEntityUpdatesExistingSeries(): void
    {
        $existingEntity = new ComicSeries();
        $existingEntity->setTitle('Old Title');
        $existingEntity->setStatus(ComicStatus::FINISHED);

        $input = new ComicSeriesInput();
        $input->title = 'New Title';
        $input->status = ComicStatus::BUYING;
        $input->type = ComicType::BD;

        $entity = $this->mapper->mapToEntity($input, $existingEntity);

        self::assertSame($existingEntity, $entity);
        self::assertSame('New Title', $entity->getTitle());
        self::assertSame(ComicStatus::BUYING, $entity->getStatus());
    }

    /**
     * Teste que les auteurs sont créés via findOrCreate.
     */
    public function testMapToEntityCreatesAuthors(): void
    {
        $authorInput = new AuthorInput();
        $authorInput->name = 'Test Author';

        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->authors = [$authorInput];

        $author = new Author();
        $author->setName('Test Author');

        $this->authorRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with('Test Author')
            ->willReturn($author);

        $entity = $this->mapper->mapToEntity($input);

        self::assertCount(1, $entity->getAuthors());
        $firstAuthor = $entity->getAuthors()->toArray()[0];
        self::assertSame('Test Author', $firstAuthor->getName());
    }

    /**
     * Teste que les auteurs avec un nom vide sont ignorés.
     */
    public function testMapToEntityIgnoresEmptyAuthorNames(): void
    {
        $emptyAuthor = new AuthorInput();
        $emptyAuthor->name = '';

        $validAuthor = new AuthorInput();
        $validAuthor->name = 'Valid Author';

        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->authors = [$emptyAuthor, $validAuthor];

        $author = new Author();
        $author->setName('Valid Author');

        $this->authorRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with('Valid Author')
            ->willReturn($author);

        $entity = $this->mapper->mapToEntity($input);

        self::assertCount(1, $entity->getAuthors());
    }

    /**
     * Teste le mapping DTO → Entity avec des tomes pour une nouvelle série.
     */
    public function testMapToEntityAddsTomesForNewSeries(): void
    {
        $tomeInput = new TomeInput();
        $tomeInput->number = 1;
        $tomeInput->bought = true;
        $tomeInput->downloaded = false;
        $tomeInput->onNas = false;
        $tomeInput->isbn = '978-1234567890';
        $tomeInput->title = 'Volume 1';

        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->tomes = [$tomeInput];

        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setBought(true);
        $tome->setIsbn('978-1234567890');
        $tome->setTitle('Volume 1');

        $this->objectMapper
            ->expects($this->once())
            ->method('map')
            ->with($tomeInput, Tome::class)
            ->willReturn($tome);

        $entity = $this->mapper->mapToEntity($input);

        self::assertCount(1, $entity->getTomes());
        $firstTome = $entity->getTomes()->toArray()[0];
        self::assertSame(1, $firstTome->getNumber());
    }

    /**
     * Teste le mapping Entity → DTO.
     */
    public function testMapToInputCreatesInput(): void
    {
        $author = new Author();
        $author->setName('Test Author');

        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setBought(true);

        $entity = new ComicSeries();
        $entity->setTitle('Test Series');
        $entity->setStatus(ComicStatus::BUYING);
        $entity->setType(ComicType::MANGA);
        $entity->setLatestPublishedIssue(10);
        $entity->setLatestPublishedIssueComplete(true);
        $entity->setIsOneShot(false);
        // isWishlist est calculé à partir du statut (BUYING → false)
        $entity->setDescription('A description');
        $entity->setPublishedDate('2024-01-15');
        $entity->setPublisher('Publisher');
        $entity->setCoverUrl('https://example.com/cover.jpg');
        $entity->addAuthor($author);
        $entity->addTome($tome);

        $authorInput = new AuthorInput();
        $authorInput->name = 'Test Author';

        $tomeInput = new TomeInput();
        $tomeInput->number = 1;
        $tomeInput->bought = true;

        $this->objectMapper
            ->method('map')
            ->willReturnCallback(static function ($source, string $target) use ($author, $authorInput, $tome, $tomeInput): AuthorInput|TomeInput|null {
                if ($source === $author && AuthorInput::class === $target) {
                    return $authorInput;
                }
                if ($source === $tome && TomeInput::class === $target) {
                    return $tomeInput;
                }

                return null;
            });

        $input = $this->mapper->mapToInput($entity);

        self::assertSame('Test Series', $input->title);
        self::assertSame(ComicStatus::BUYING, $input->status);
        self::assertSame(ComicType::MANGA, $input->type);
        self::assertSame(10, $input->latestPublishedIssue);
        self::assertTrue($input->latestPublishedIssueComplete);
        self::assertFalse($input->isOneShot);
        self::assertSame('A description', $input->description);
        self::assertSame('2024-01-15', $input->publishedDate);
        self::assertSame('Publisher', $input->publisher);
        self::assertSame('https://example.com/cover.jpg', $input->coverUrl);
        self::assertCount(1, $input->authors);
        self::assertCount(1, $input->tomes);
    }

    /**
     * Teste la synchronisation des tomes lors de l'édition : mise à jour.
     */
    public function testMapToEntityUpdatesTomesForExistingSeries(): void
    {
        $existingTome = new Tome();
        $existingTome->setNumber(1);
        $existingTome->setBought(false);
        $existingTome->setDownloaded(false);
        $existingTome->setOnNas(false);

        $existingEntity = new ComicSeries();
        $existingEntity->setTitle('Test Series');
        $existingEntity->addTome($existingTome);

        $tomeInput = new TomeInput();
        $tomeInput->number = 1;
        $tomeInput->bought = true;
        $tomeInput->downloaded = true;
        $tomeInput->onNas = true;
        $tomeInput->isbn = '978-1234567890';
        $tomeInput->title = 'Updated Title';

        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->tomes = [$tomeInput];

        $entity = $this->mapper->mapToEntity($input, $existingEntity);

        self::assertCount(1, $entity->getTomes());
        $tome = $entity->getTomes()->toArray()[0];
        self::assertTrue($tome->isBought());
        self::assertTrue($tome->isDownloaded());
        self::assertTrue($tome->isOnNas());
        self::assertSame('978-1234567890', $tome->getIsbn());
        self::assertSame('Updated Title', $tome->getTitle());
    }

    /**
     * Teste la synchronisation des tomes lors de l'édition : suppression.
     */
    public function testMapToEntityRemovesDeletedTomes(): void
    {
        $tome1 = new Tome();
        $tome1->setNumber(1);

        $tome2 = new Tome();
        $tome2->setNumber(2);

        $existingEntity = new ComicSeries();
        $existingEntity->setTitle('Test Series');
        $existingEntity->addTome($tome1);
        $existingEntity->addTome($tome2);

        // Input ne contient que le tome 1, donc tome 2 doit être supprimé
        $tomeInput = new TomeInput();
        $tomeInput->number = 1;
        $tomeInput->bought = false;
        $tomeInput->downloaded = false;
        $tomeInput->onNas = false;

        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->tomes = [$tomeInput];

        $entity = $this->mapper->mapToEntity($input, $existingEntity);

        self::assertCount(1, $entity->getTomes());
        $firstTome = $entity->getTomes()->toArray()[0];
        self::assertSame(1, $firstTome->getNumber());
    }

    /**
     * Teste la synchronisation des tomes lors de l'édition : ajout.
     */
    public function testMapToEntityAddsNewTomesToExistingSeries(): void
    {
        $existingTome = new Tome();
        $existingTome->setNumber(1);

        $existingEntity = new ComicSeries();
        $existingEntity->setTitle('Test Series');
        $existingEntity->addTome($existingTome);

        $existingTomeInput = new TomeInput();
        $existingTomeInput->number = 1;
        $existingTomeInput->bought = false;
        $existingTomeInput->downloaded = false;
        $existingTomeInput->onNas = false;

        $newTomeInput = new TomeInput();
        $newTomeInput->number = 2;
        $newTomeInput->bought = true;
        $newTomeInput->downloaded = false;
        $newTomeInput->onNas = false;

        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->tomes = [$existingTomeInput, $newTomeInput];

        $newTome = new Tome();
        $newTome->setNumber(2);
        $newTome->setBought(true);

        $this->objectMapper
            ->expects($this->once())
            ->method('map')
            ->with($newTomeInput, Tome::class)
            ->willReturn($newTome);

        $entity = $this->mapper->mapToEntity($input, $existingEntity);

        self::assertCount(2, $entity->getTomes());
    }

    /**
     * Teste qu'un one-shot sans tomes crée automatiquement un tome n°1.
     */
    public function testMapToEntityCreatesDefaultTomeForOneShotWithoutTomes(): void
    {
        $input = new ComicSeriesInput();
        $input->title = 'One-Shot Test';
        $input->isOneShot = true;
        $input->tomes = [];

        $defaultTome = new Tome();
        $defaultTome->setNumber(1);

        $this->objectMapper
            ->expects($this->once())
            ->method('map')
            ->willReturn($defaultTome);

        $entity = $this->mapper->mapToEntity($input);

        self::assertCount(1, $entity->getTomes());
        $tome = $entity->getTomes()->first();
        self::assertSame(1, $tome->getNumber());
    }

    /**
     * Teste qu'un one-shot avec un tome existant ne crée pas de doublon.
     */
    public function testMapToEntityDoesNotDuplicateTomeForOneShotWithExistingTome(): void
    {
        $tomeInput = new TomeInput();
        $tomeInput->number = 1;
        $tomeInput->bought = true;
        $tomeInput->downloaded = false;
        $tomeInput->isbn = '978-1234567890';
        $tomeInput->onNas = false;

        $input = new ComicSeriesInput();
        $input->title = 'One-Shot Test';
        $input->isOneShot = true;
        $input->tomes = [$tomeInput];

        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setBought(true);
        $tome->setIsbn('978-1234567890');

        $this->objectMapper
            ->expects($this->once())
            ->method('map')
            ->with($tomeInput, Tome::class)
            ->willReturn($tome);

        $entity = $this->mapper->mapToEntity($input);

        self::assertCount(1, $entity->getTomes());
        self::assertSame('978-1234567890', $entity->getTomes()->first()->getIsbn());
    }

    /**
     * Teste qu'un one-shot existant sans tome crée le tome n°1 en édition.
     */
    public function testMapToEntityCreatesDefaultTomeForExistingOneShotWithoutTomes(): void
    {
        $existingEntity = new ComicSeries();
        $existingEntity->setTitle('Existing One-Shot');

        $input = new ComicSeriesInput();
        $input->title = 'Existing One-Shot';
        $input->isOneShot = true;
        $input->tomes = [];

        $defaultTome = new Tome();
        $defaultTome->setNumber(1);

        $this->objectMapper
            ->expects($this->once())
            ->method('map')
            ->willReturn($defaultTome);

        $entity = $this->mapper->mapToEntity($input, $existingEntity);

        self::assertCount(1, $entity->getTomes());
        self::assertSame(1, $entity->getTomes()->first()->getNumber());
    }

    /**
     * Teste la suppression de la couverture via deleteCover.
     */
    public function testMapToEntityDeletesCoverWhenRequested(): void
    {
        $existingEntity = new ComicSeries();
        $existingEntity->setTitle('Test Series');
        $existingEntity->setCoverImage('existing-cover.jpg');

        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->deleteCover = true;

        $this->coverRemover
            ->expects($this->once())
            ->method('remove')
            ->with($existingEntity);

        $this->mapper->mapToEntity($input, $existingEntity);
    }

    /**
     * Teste que deleteCover ne supprime rien si aucune image n'existe.
     */
    public function testMapToEntityDoesNotDeleteCoverWhenNoneExists(): void
    {
        $existingEntity = new ComicSeries();
        $existingEntity->setTitle('Test Series');
        // Pas de coverImage

        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->deleteCover = true;

        $this->coverRemover
            ->expects($this->never())
            ->method('remove');

        $this->mapper->mapToEntity($input, $existingEntity);
    }

    /**
     * Teste que mapToInput normalise une date avec heure en date seule.
     */
    public function testMapToInputNormalizesDateWithTime(): void
    {
        $entity = new ComicSeries();
        $entity->setTitle('Test');
        $entity->setPublishedDate('2023-01-15 10:30:00');

        $input = $this->mapper->mapToInput($entity);

        self::assertSame('2023-01-15', $input->publishedDate);
    }

    /**
     * Teste que mapToInput normalise une année seule en date complète.
     */
    public function testMapToInputNormalizesYearOnlyDate(): void
    {
        $entity = new ComicSeries();
        $entity->setTitle('Test');
        $entity->setPublishedDate('2023');

        $input = $this->mapper->mapToInput($entity);

        self::assertSame('2023-01-01', $input->publishedDate);
    }

    /**
     * Teste que mapToInput normalise une date année-mois en date complète.
     */
    public function testMapToInputNormalizesYearMonthDate(): void
    {
        $entity = new ComicSeries();
        $entity->setTitle('Test');
        $entity->setPublishedDate('2023-06');

        $input = $this->mapper->mapToInput($entity);

        self::assertSame('2023-06-01', $input->publishedDate);
    }

    /**
     * Teste que mapToInput conserve une date déjà au bon format.
     */
    public function testMapToInputKeepsValidDate(): void
    {
        $entity = new ComicSeries();
        $entity->setTitle('Test');
        $entity->setPublishedDate('2024-01-15');

        $input = $this->mapper->mapToInput($entity);

        self::assertSame('2024-01-15', $input->publishedDate);
    }

    /**
     * Teste que mapToInput gère une date nulle.
     */
    public function testMapToInputHandlesNullDate(): void
    {
        $entity = new ComicSeries();
        $entity->setTitle('Test');
        $entity->setPublishedDate(null);

        $input = $this->mapper->mapToInput($entity);

        self::assertNull($input->publishedDate);
    }

    /**
     * Teste que les auteurs sont effacés avant réassignation.
     */
    public function testMapToEntityClearsExistingAuthors(): void
    {
        $existingAuthor = new Author();
        $existingAuthor->setName('Old Author');

        $existingEntity = new ComicSeries();
        $existingEntity->setTitle('Test Series');
        $existingEntity->addAuthor($existingAuthor);

        $newAuthorInput = new AuthorInput();
        $newAuthorInput->name = 'New Author';

        $input = new ComicSeriesInput();
        $input->title = 'Test Series';
        $input->authors = [$newAuthorInput];

        $newAuthor = new Author();
        $newAuthor->setName('New Author');

        $this->authorRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with('New Author')
            ->willReturn($newAuthor);

        $entity = $this->mapper->mapToEntity($input, $existingEntity);

        self::assertCount(1, $entity->getAuthors());
        $firstAuthor = $entity->getAuthors()->toArray()[0];
        self::assertSame('New Author', $firstAuthor->getName());
    }
}
