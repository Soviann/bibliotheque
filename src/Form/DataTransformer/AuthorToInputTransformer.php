<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Dto\Input\AuthorInput;
use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * Transforme les AuthorInput en entités Author pour l'autocomplete et vice-versa.
 *
 * @implements DataTransformerInterface<list<AuthorInput>, list<Author>>
 */
class AuthorToInputTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly ObjectMapperInterface $mapper,
    ) {
    }

    /**
     * DTO → Entity (pour affichage dans le formulaire / autocomplete).
     *
     * @param list<AuthorInput>|null $value
     *
     * @return list<Author>
     */
    public function transform(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        return \array_values(\array_filter(\array_map(
            fn (AuthorInput $input) => $this->authorRepository->findOneBy(['name' => $input->name]),
            $value
        )));
    }

    /**
     * Entity → DTO (après soumission du formulaire).
     *
     * @param list<Author>|Collection<int, Author>|null $value
     *
     * @return list<AuthorInput>
     */
    public function reverseTransform(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        $authors = $value instanceof Collection ? $value->toArray() : $value;

        return \array_values(\array_map(
            fn (Author $author) => $this->mapper->map($author, AuthorInput::class),
            $authors
        ));
    }
}
