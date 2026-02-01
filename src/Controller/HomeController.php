<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ComicFilters;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        #[MapQueryString] ComicFilters $filters,
        ComicSeriesRepository $comicSeriesRepository,
    ): Response {
        $comics = $comicSeriesRepository->findWithFilters([
            'isWishlist' => false,
            'onNas' => $filters->getOnNas(),
            'search' => $filters->getSearch(),
            'sort' => $filters->sort,
            'status' => $filters->getStatus(),
            'type' => $filters->getType(),
        ]);

        // Available statuses for library (exclude wishlist status)
        $statuses = \array_filter(
            ComicStatus::cases(),
            static fn (ComicStatus $s): bool => ComicStatus::WISHLIST !== $s
        );

        return $this->render('home/index.html.twig', [
            'comics' => $comics,
            'currentNas' => $filters->nas,
            'currentSearch' => $filters->q ?? '',
            'currentSort' => $filters->sort,
            'currentStatus' => $filters->getStatus(),
            'currentType' => $filters->getType(),
            'statuses' => $statuses,
            'types' => ComicType::cases(),
        ]);
    }
}
