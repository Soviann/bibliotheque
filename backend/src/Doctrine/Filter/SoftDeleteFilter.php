<?php

declare(strict_types=1);

namespace App\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Knp\DoctrineBehaviors\Contract\Entity\SoftDeletableInterface;

/**
 * Filtre SQL Doctrine qui exclut les entités soft-deleted des requêtes.
 *
 * Ajoute automatiquement « WHERE deleted_at IS NULL » sur toute entité
 * implémentant SoftDeletableInterface.
 */
final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!\is_a($targetEntity->getName(), SoftDeletableInterface::class, true)) {
            return '';
        }

        return \sprintf('%s.deleted_at IS NULL', $targetTableAlias);
    }
}
