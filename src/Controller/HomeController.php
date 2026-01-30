<?php

namespace App\Controller;

use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, ComicSeriesRepository $comicSeriesRepository): Response
    {
        // Type filter
        $typeFilter = $request->query->get('type');
        $type = $typeFilter ? ComicType::tryFrom($typeFilter) : null;

        // Status filter
        $statusFilter = $request->query->get('status');
        $status = $statusFilter ? ComicStatus::tryFrom($statusFilter) : null;

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
            'isWishlist' => false,
            'onNas' => $onNas,
            'search' => $search ?: null,
            'sort' => $sort,
            'status' => $status,
            'type' => $type,
        ]);

        // Available statuses for library (exclude wishlist status)
        $statuses = array_filter(
            ComicStatus::cases(),
            fn (ComicStatus $s) => $s !== ComicStatus::WISHLIST
        );

        return $this->render('home/index.html.twig', [
            'comics' => $comics,
            'currentNas' => $nasFilter,
            'currentSearch' => $search,
            'currentSort' => $sort,
            'currentStatus' => $status,
            'currentType' => $type,
            'statuses' => $statuses,
            'types' => ComicType::cases(),
        ]);
    }
}
