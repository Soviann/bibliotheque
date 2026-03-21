<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrichmentLog;
use App\Entity\EnrichmentProposal;
use App\Enum\EnrichmentAction;
use App\Enum\ProposalStatus;
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
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param EnrichmentProposal $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnrichmentProposal
    {
        if (ProposalStatus::PENDING !== $data->getStatus()) {
            throw new BadRequestHttpException('Seules les propositions en attente peuvent être rejetées.');
        }

        $data->reject();

        $log = new EnrichmentLog(
            action: EnrichmentAction::REJECTED,
            comicSeries: $data->getComicSeries(),
            confidence: $data->getConfidence(),
            field: $data->getField(),
            newValue: $data->getProposedValue(),
            oldValue: $data->getCurrentValue(),
            source: $data->getSource(),
        );
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $data;
    }
}
