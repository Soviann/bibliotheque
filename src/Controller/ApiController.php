<?php

namespace App\Controller;

use App\Repository\ComicSeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/comics', name: 'api_comics', methods: ['GET'])]
    public function comics(ComicSeriesRepository $comicSeriesRepository): JsonResponse
    {
        $comics = $comicSeriesRepository->findAllForApi();

        return $this->json($comics);
    }
}
