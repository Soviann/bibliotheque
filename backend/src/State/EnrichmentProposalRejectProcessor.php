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

/**
 * Rejette une proposition d'enrichissement.
 *
 * @implements ProcessorInterface<EnrichmentProposal, EnrichmentProposal>
 */
final readonly class EnrichmentProposalRejectProcessor implements ProcessorInterface
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
            throw new BadRequestHttpException('Seules les propositions en attente ou pré-approuvées peuvent être rejetées.');
        }

        // PRE_ACCEPTED : la valeur a été appliquée, il faut la reverter
        if (ProposalStatus::PRE_ACCEPTED === $data->getStatus()) {
            $this->enrichmentService->revertFieldValue(
                $data->getComicSeries(),
                $data->getField(),
                $data->getCurrentValue(),
            );
        }

        $data->reject();
        $this->entityManager->flush();

        return $data;
    }
}
