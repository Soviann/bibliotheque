<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrichmentProposal;
use App\Enum\ProposalStatus;
use App\Service\Enrichment\EnrichmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Accepte une proposition d'enrichissement et applique la valeur à la série.
 *
 * @implements ProcessorInterface<EnrichmentProposal, EnrichmentProposal>
 */
final readonly class EnrichmentProposalAcceptProcessor implements ProcessorInterface
{
    public function __construct(
        private EnrichmentService $enrichmentService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param EnrichmentProposal $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnrichmentProposal
    {
        if (!\in_array($data->getStatus(), [ProposalStatus::PENDING, ProposalStatus::PRE_ACCEPTED], true)) {
            throw new BadRequestHttpException('Seules les propositions en attente ou pré-approuvées peuvent être acceptées.');
        }

        $series = $data->getComicSeries();
        $field = $data->getField();

        if (ProposalStatus::PENDING === $data->getStatus()) {
            // Vérifie que la valeur n'a pas changé depuis la proposition
            $currentValue = $this->enrichmentService->getSeriesValue($series, $field);

            if ($currentValue !== $data->getCurrentValue()) {
                throw new ConflictHttpException('La valeur du champ a changé depuis la proposition. Veuillez la rejeter et relancer un enrichissement.');
            }

            $this->enrichmentService->applyFieldValue($series, $field, $data->getProposedValue());
        }

        // PRE_ACCEPTED : la valeur est déjà appliquée, on confirme simplement

        $data->accept();
        $this->entityManager->flush();

        return $data;
    }
}
