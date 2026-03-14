<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Author;
use App\Repository\AuthorRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gère le POST d'un auteur en « find or create ».
 *
 * Si un auteur avec le même nom existe déjà, retourne l'existant
 * au lieu de lever une erreur UniqueEntity.
 *
 * @implements ProcessorInterface<Author, Author>
 */
final readonly class AuthorCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private AuthorRepository $authorRepository,
        /** @var ProcessorInterface<Author, Author> */
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    /**
     * @param Author $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Author
    {
        $existing = $this->authorRepository->findOneBy(['name' => $data->getName()]);

        if (null !== $existing) {
            return $existing;
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
