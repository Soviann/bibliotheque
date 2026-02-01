<?php

declare(strict_types=1);

namespace App\Tests\Form\DataTransformer;

use App\Dto\Input\AuthorInput;
use App\Entity\Author;
use App\Form\DataTransformer\AuthorToInputTransformer;
use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * Tests unitaires pour AuthorToInputTransformer.
 */
#[CoversClass(AuthorToInputTransformer::class)]
class AuthorToInputTransformerTest extends TestCase
{
    private AuthorToInputTransformer $transformer;
    private AuthorRepository&MockObject $authorRepository;
    private ObjectMapperInterface&MockObject $objectMapper;

    protected function setUp(): void
    {
        $this->authorRepository = $this->createMock(AuthorRepository::class);
        $this->objectMapper = $this->createMock(ObjectMapperInterface::class);
        $this->transformer = new AuthorToInputTransformer($this->authorRepository, $this->objectMapper);
    }

    /**
     * Teste transform() avec null.
     */
    public function testTransformWithNull(): void
    {
        $result = $this->transformer->transform(null);

        self::assertSame([], $result);
    }

    /**
     * Teste transform() avec un tableau vide.
     */
    public function testTransformWithEmptyArray(): void
    {
        $result = $this->transformer->transform([]);

        self::assertSame([], $result);
    }

    /**
     * Teste transform() convertit les AuthorInput en Author.
     */
    public function testTransformConvertsInputsToEntities(): void
    {
        $input1 = new AuthorInput();
        $input1->name = 'Author One';

        $input2 = new AuthorInput();
        $input2->name = 'Author Two';

        $author1 = new Author();
        $author1->setName('Author One');

        $author2 = new Author();
        $author2->setName('Author Two');

        $this->authorRepository
            ->method('findOneBy')
            ->willReturnMap([
                [['name' => 'Author One'], null, $author1],
                [['name' => 'Author Two'], null, $author2],
            ]);

        $result = $this->transformer->transform([$input1, $input2]);

        self::assertCount(2, $result);
        self::assertSame($author1, $result[0]);
        self::assertSame($author2, $result[1]);
    }

    /**
     * Teste transform() filtre les auteurs non trouvés.
     */
    public function testTransformFiltersNonExistentAuthors(): void
    {
        $input1 = new AuthorInput();
        $input1->name = 'Existing Author';

        $input2 = new AuthorInput();
        $input2->name = 'Non Existent';

        $author = new Author();
        $author->setName('Existing Author');

        $this->authorRepository
            ->method('findOneBy')
            ->willReturnMap([
                [['name' => 'Existing Author'], null, $author],
                [['name' => 'Non Existent'], null, null],
            ]);

        $result = $this->transformer->transform([$input1, $input2]);

        self::assertCount(1, $result);
        self::assertSame($author, $result[0]);
    }

    /**
     * Teste reverseTransform() avec null.
     */
    public function testReverseTransformWithNull(): void
    {
        $result = $this->transformer->reverseTransform(null);

        self::assertSame([], $result);
    }

    /**
     * Teste reverseTransform() avec un tableau vide.
     */
    public function testReverseTransformWithEmptyArray(): void
    {
        $result = $this->transformer->reverseTransform([]);

        self::assertSame([], $result);
    }

    /**
     * Teste reverseTransform() convertit les Author en AuthorInput.
     */
    public function testReverseTransformConvertsEntitiesToInputs(): void
    {
        $author1 = new Author();
        $author1->setName('Author One');

        $author2 = new Author();
        $author2->setName('Author Two');

        $input1 = new AuthorInput();
        $input1->name = 'Author One';

        $input2 = new AuthorInput();
        $input2->name = 'Author Two';

        $this->objectMapper
            ->method('map')
            ->willReturnMap([
                [$author1, AuthorInput::class, [], $input1],
                [$author2, AuthorInput::class, [], $input2],
            ]);

        $result = $this->transformer->reverseTransform([$author1, $author2]);

        self::assertCount(2, $result);
        self::assertSame('Author One', $result[0]->name);
        self::assertSame('Author Two', $result[1]->name);
    }

    /**
     * Teste reverseTransform() avec une Collection Doctrine.
     */
    public function testReverseTransformWithCollection(): void
    {
        $author = new Author();
        $author->setName('Author One');

        $collection = new ArrayCollection([$author]);

        $input = new AuthorInput();
        $input->name = 'Author One';

        $this->objectMapper
            ->expects($this->once())
            ->method('map')
            ->with($author, AuthorInput::class)
            ->willReturn($input);

        $result = $this->transformer->reverseTransform($collection);

        self::assertCount(1, $result);
        self::assertSame('Author One', $result[0]->name);
    }

    /**
     * Teste que reverseTransform() retourne une liste (array_values).
     */
    public function testReverseTransformReturnsListNotIndexedArray(): void
    {
        $author = new Author();
        $author->setName('Author');

        $input = new AuthorInput();
        $input->name = 'Author';

        $this->objectMapper
            ->method('map')
            ->willReturn($input);

        $result = $this->transformer->reverseTransform([$author]);

        self::assertSame([0], \array_keys($result));
    }
}
