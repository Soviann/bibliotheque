<?php

namespace App\Controller;

use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WishlistController extends AbstractController
{
    #[Route('/wishlist', name: 'app_wishlist')]
    public function index(Request $request, ComicSeriesRepository $comicSeriesRepository): Response
    {
        // Type filter
        $typeFilter = $request->query->get('type');
        $type = $typeFilter ? ComicType::tryFrom($typeFilter) : null;

        // NAS filter
        $nasFilter = $request->query->get('nas');
        $onNas = match ($nasFilter) {
            '1' => true,
            '0' => false,
            default => null,
        };

        // Search filter
        $search = $request->query->get('q', '');

        // Sort
        $sort = $request->query->get('sort', 'title_asc');

        $comics = $comicSeriesRepository->findWithFilters([
            'isWishlist' => true,
            'onNas' => $onNas,
            'search' => $search ?: null,
            'sort' => $sort,
            'type' => $type,
        ]);

        return $this->render('wishlist/index.html.twig', [
            'comics' => $comics,
            'currentNas' => $nasFilter,
            'currentSearch' => $search,
            'currentSort' => $sort,
            'currentType' => $type,
            'types' => ComicType::cases(),
        ]);
    }
}
