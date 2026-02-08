<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ComicSeries;
use App\Service\CoverRemoverInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/trash')]
class TrashController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CoverRemoverInterface $coverRemover,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_trash', methods: ['GET'])]
    public function index(): Response
    {
        $this->entityManager->getFilters()->disable('soft_delete');

        $deletedSeries = $this->entityManager->getRepository(ComicSeries::class)
            ->createQueryBuilder('c')
            ->where('c.deletedAt IS NOT NULL')
            ->orderBy('c.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $this->entityManager->getFilters()->enable('soft_delete');

        return $this->render('trash/index.html.twig', [
            'deletedSeries' => $deletedSeries,
        ]);
    }

    #[Route('/{id}/restore', name: 'app_trash_restore', methods: ['POST'])]
    public function restore(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('restore'.$id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('app_trash');
        }

        $comic = $this->findSoftDeletedSeries($id);

        $comic->restore();
        $this->entityManager->flush();

        $this->addFlash('success', 'La série a été restaurée.');

        return $this->redirectToRoute('app_trash');
    }

    #[Route('/{id}/permanent-delete', name: 'app_trash_permanent_delete', methods: ['POST'])]
    public function permanentDelete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('permanent-delete'.$id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('app_trash');
        }

        $comic = $this->findSoftDeletedSeries($id);

        // Supprimer la couverture physique
        $this->coverRemover->remove($comic);

        // Suppression DBAL pour bypasser le subscriber soft-delete
        $this->connection->delete('comic_series_author', ['comic_series_id' => $id]);
        $this->connection->delete('tome', ['comic_series_id' => $id]);
        $this->connection->delete('comic_series', ['id' => $id]);

        $this->addFlash('success', 'La série a été définitivement supprimée.');

        return $this->redirectToRoute('app_trash');
    }

    /**
     * Recherche une série soft-deleted par son ID.
     *
     * @throws NotFoundHttpException si la série n'existe pas ou n'est pas soft-deleted
     */
    private function findSoftDeletedSeries(int $id): ComicSeries
    {
        $this->entityManager->getFilters()->disable('soft_delete');

        $comic = $this->entityManager->getRepository(ComicSeries::class)->find($id);

        $this->entityManager->getFilters()->enable('soft_delete');

        if (!$comic instanceof ComicSeries || !$comic->isDeleted()) {
            throw $this->createNotFoundException();
        }

        return $comic;
    }
}
