<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\Author;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO pour la création/édition d'un auteur.
 */
#[Map(target: Author::class)]
class AuthorInput
{
    #[Assert\NotBlank(message: 'Le nom de l\'auteur est obligatoire.')]
    public string $name = '';
}
