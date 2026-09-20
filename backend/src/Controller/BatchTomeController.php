<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tome;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contrôleur pour la création par lot de tomes dans une série.
 */
#[IsGranted('ROLE_USER')]
final readonly class BatchTomeController
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/comic_series/{id}/tomes/batch', name: 'api_comic_series_tomes_batch', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $series = $this->comicSeriesRepository->find($id);

        if (null === $series || null !== $series->getDeletedAt()) {
            return new JsonResponse(['error' => 'Série non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        $payload = \json_decode($request->getContent(), true);

        /** @var list<mixed> $tomesData */
        $tomesData = \is_array($payload) && isset($payload['tomes']) && \is_array($payload['tomes'])
            ? $payload['tomes']
            : (\is_array($payload) && \array_is_list($payload) ? $payload : []);

        if ([] === $tomesData) {
            return new JsonResponse(['error' => 'Aucun tome fourni.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var list<Tome> $createdTomes */
        $createdTomes = [];

        foreach ($tomesData as $item) {
            if (!\is_array($item)) {
                return new JsonResponse(['error' => 'Format de données de tome invalide.'], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($item['number']) || !\is_numeric($item['number'])) {
                return new JsonResponse(['error' => 'Numéro de tome requis.'], Response::HTTP_BAD_REQUEST);
            }

            $number = (int) $item['number'];
            if ($number < 0) {
                return new JsonResponse(['error' => 'Numéro de tome invalide.'], Response::HTTP_BAD_REQUEST);
            }

            $tome = new Tome();
            $tome->setBought((bool) ($item['bought'] ?? false));
            $tome->setComicSeries($series);
            $tome->setIsHorsSerie((bool) ($item['isHorsSerie'] ?? false));
            $tome->setNumber($number);
            $tome->setOnNas((bool) ($item['onNas'] ?? false));
            $tome->setRead((bool) ($item['read'] ?? false));

            if (isset($item['tomeEnd']) && \is_numeric($item['tomeEnd'])) {
                $tome->setTomeEnd((int) $item['tomeEnd']);
            }

            if (isset($item['isbn']) && \is_string($item['isbn']) && '' !== \trim($item['isbn'])) {
                $tome->setIsbn(\trim($item['isbn']));
            }

            if (isset($item['title']) && \is_string($item['title']) && '' !== \trim($item['title'])) {
                $tome->setTitle(\trim($item['title']));
            }

            $errors = $this->validator->validate($tome);
            if (\count($errors) > 0) {
                return new JsonResponse(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($tome);
            $createdTomes[] = $tome;
        }

        $this->entityManager->flush();

        $data = \array_map(static fn (Tome $t): array => [
            '@id' => '/api/tomes/'.$t->getId(),
            'bought' => $t->isBought(),
            'createdAt' => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'id' => $t->getId(),
            'isHorsSerie' => $t->isHorsSerie(),
            'isbn' => $t->getIsbn(),
            'number' => $t->getNumber(),
            'onNas' => $t->isOnNas(),
            'read' => $t->isRead(),
            'title' => $t->getTitle(),
            'tomeEnd' => $t->getTomeEnd(),
            'updatedAt' => $t->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ], $createdTomes);

        return new JsonResponse($data, Response::HTTP_CREATED);
    }
}
