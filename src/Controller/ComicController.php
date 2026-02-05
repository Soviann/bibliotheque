<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\ComicSeriesInput;
use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Form\ComicSeriesType;
use App\Service\ComicSeriesMapper;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comic')]
class ComicController extends AbstractController
{
    public function __construct(
        private readonly ComicSeriesMapper $comicSeriesMapper,
    ) {
    }

    #[Route('/{id}', name: 'app_comic_show', methods: ['GET'], priority: -1)]
    public function show(ComicSeries $comic): Response
    {
        return $this->render('comic/show.html.twig', [
            'comic' => $comic,
        ]);
    }

    #[Route('/new', name: 'app_comic_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $input = new ComicSeriesInput();

        // Présélectionner le statut WISHLIST si on vient de la page wishlist
        if ($request->query->getBoolean('wishlist')) {
            $input->status = ComicStatus::WISHLIST;
        }

        $form = $this->createForm(ComicSeriesType::class, $input);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $comic = $this->comicSeriesMapper->mapToEntity($input);
                $entityManager->persist($comic);
                $entityManager->flush();

                $this->addFlash('success', 'La série a été ajoutée avec succès.');

                if ($comic->isWishlist()) {
                    return $this->redirectToRoute('app_wishlist');
                }

                return $this->redirectToRoute('app_home');
            } catch (DriverException) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.');
            }
        }

        return $this->render('comic/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_comic_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ComicSeries $comic, EntityManagerInterface $entityManager): Response
    {
        $input = $this->comicSeriesMapper->mapToInput($comic);
        $form = $this->createForm(ComicSeriesType::class, $input);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->comicSeriesMapper->mapToEntity($input, $comic);
                $entityManager->flush();

                $this->addFlash('success', 'La série a été modifiée avec succès.');

                if ($comic->isWishlist()) {
                    return $this->redirectToRoute('app_wishlist');
                }

                return $this->redirectToRoute('app_home');
            } catch (DriverException) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.');
            }
        }

        return $this->render('comic/edit.html.twig', [
            'comic' => $comic,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_comic_delete', methods: ['POST'])]
    public function delete(Request $request, ComicSeries $comic, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$comic->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('app_home');
        }

        $wasWishlist = $comic->isWishlist();

        try {
            $entityManager->remove($comic);
            $entityManager->flush();

            $this->addFlash('success', 'La série a été supprimée avec succès.');

            if ($wasWishlist) {
                return $this->redirectToRoute('app_wishlist');
            }
        } catch (DriverException) {
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression. Veuillez réessayer.');
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/{id}/to-library', name: 'app_comic_to_library', methods: ['POST'])]
    public function toLibrary(Request $request, ComicSeries $comic, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('to-library'.$comic->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('app_home');
        }

        // Passer le statut à BUYING fait automatiquement que isWishlist() retourne false
        $comic->setStatus(ComicStatus::BUYING);
        $entityManager->flush();

        $this->addFlash('success', 'La série a été déplacée vers la bibliothèque.');

        return $this->redirectToRoute('app_home');
    }
}
