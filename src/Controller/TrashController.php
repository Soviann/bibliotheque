<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ComicSeriesRepository;
use App\Service\ComicSeriesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/trash')]
class TrashController extends AbstractController
{
    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly ComicSeriesService $comicSeriesService,
    ) {
    }

    #[Route('', name: 'app_trash', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('trash/index.html.twig', [
            'deletedSeries' => $this->comicSeriesRepository->findSoftDeleted(),
        ]);
    }

    #[Route('/{id}/restore', name: 'app_trash_restore', methods: ['POST'])]
    public function restore(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('restore'.$id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('app_trash');
        }

        $comic = $this->comicSeriesRepository->findSoftDeletedById($id);

        if (null === $comic) {
            throw $this->createNotFoundException();
        }

        $this->comicSeriesService->restore($comic);

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

        $comic = $this->comicSeriesRepository->findSoftDeletedById($id);

        if (null === $comic) {
            throw $this->createNotFoundException();
        }

        $this->comicSeriesService->permanentDelete($id, $comic);

        $this->addFlash('success', 'La série a été définitivement supprimée.');

        return $this->redirectToRoute('app_trash');
    }
}
