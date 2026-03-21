<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrichmentLog;
use App\Entity\EnrichmentProposal;
use App\Enum\EnrichableField;
use App\Enum\EnrichmentAction;
use App\Repository\AuthorRepository;
use App\Service\CoverDownloader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Accepte une proposition d'enrichissement et applique la valeur à la série.
 *
 * @implements ProcessorInterface<EnrichmentProposal, EnrichmentProposal>
 */
final readonly class EnrichmentProposalAcceptProcessor implements ProcessorInterface
{
    public function __construct(
        private AuthorRepository $authorRepository,
        private CoverDownloader $coverDownloader,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param EnrichmentProposal $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnrichmentProposal
    {
        if ($data->getStatus() !== \App\Enum\ProposalStatus::PENDING) {
            throw new BadRequestHttpException('Seules les propositions en attente peuvent être acceptées.');
        }

        $series = $data->getComicSeries();
        $field = $data->getField();
        $proposedValue = $data->getProposedValue();

        $this->applyValue($data);

        $data->accept();

        $log = new EnrichmentLog(
            action: EnrichmentAction::ACCEPTED,
            comicSeries: $series,
            confidence: $data->getConfidence(),
            field: $field,
            newValue: $proposedValue,
            oldValue: $data->getCurrentValue(),
            source: $data->getSource(),
        );
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $data;
    }

    private function applyValue(EnrichmentProposal $proposal): void
    {
        $series = $proposal->getComicSeries();
        $value = $proposal->getProposedValue();

        match ($proposal->getField()) {
            EnrichableField::AMAZON_URL => $series->setAmazonUrl((string) $value),
            EnrichableField::AUTHORS => $this->applyAuthors($series, $value),
            EnrichableField::COVER => $this->coverDownloader->downloadAndStore($series, (string) $value),
            EnrichableField::DESCRIPTION => $series->setDescription((string) $value),
            EnrichableField::ISBN => null, // ISBN is informational, no setter on series
            EnrichableField::IS_ONE_SHOT => $series->setIsOneShot((bool) $value),
            EnrichableField::LATEST_PUBLISHED_ISSUE => $series->setLatestPublishedIssue((int) $value),
            EnrichableField::PUBLISHER => $series->setPublisher((string) $value),
        };
    }

    private function applyAuthors(\App\Entity\ComicSeries $series, mixed $value): void
    {
        if (!\is_string($value) || '' === $value) {
            return;
        }

        $names = \array_map(\trim(...), \explode(',', $value));
        $authors = $this->authorRepository->findOrCreateMultiple($names);

        foreach ($authors as $author) {
            $series->addAuthor($author);
        }
    }
}
