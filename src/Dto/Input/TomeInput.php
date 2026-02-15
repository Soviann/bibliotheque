<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\Tome;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO pour la création/édition d'un tome.
 */
#[Map(target: Tome::class)]
class TomeInput
{
    #[Assert\NotNull(message: 'Le numéro du tome est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le numéro du tome doit être positif ou zéro.')]
    public int $number = 0;

    public bool $bought = false;

    public bool $downloaded = false;

    public bool $onNas = false;

    public bool $read = false;

    public ?string $isbn = null;

    public ?string $title = null;
}
