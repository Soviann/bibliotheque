<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ComicFilters;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class WishlistController extends AbstractController
{
    #[Route('/wishlist', name: 'app_wishlist')]
    public function index(
        #[MapQueryString] ComicFilters $filters,
        ComicSeriesRepository $comicSeriesRepository,
    ): Response {
        $comics = $comicSeriesRepository->findWithFilters([
            'isWishlist' => true,
            'onNas' => $filters->getOnNas(),
            'search' => $filters->getSearch(),
            'sort' => $filters->sort,
            'type' => $filters->getType(),
        ]);

        return $this->render('wishlist/index.html.twig', [
            'comics' => $comics,
            'currentNas' => $filters->nas,
            'currentSearch' => $filters->q ?? '',
            'currentSort' => $filters->sort,
            'currentType' => $filters->getType(),
            'types' => ComicType::cases(),
        ]);
    }
}
