<?php

namespace App\Controller;

use App\Repository\ComicSeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search')]
    public function index(Request $request, ComicSeriesRepository $comicSeriesRepository): Response
    {
        $query = $request->query->get('q', '');
        $comics = [];

        if ($query !== '') {
            $comics = $comicSeriesRepository->search($query);
        }

        return $this->render('search/index.html.twig', [
            'comics' => $comics,
            'query' => $query,
        ]);
    }
}
