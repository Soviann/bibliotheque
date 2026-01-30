<?php

namespace App\Controller;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Form\ComicSeriesType;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comic')]
class ComicController extends AbstractController
{
    #[Route('/new', name: 'app_comic_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $comic = new ComicSeries();
        $form = $this->createForm(ComicSeriesType::class, $comic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($comic);
            $entityManager->flush();

            $this->addFlash('success', 'La série a été ajoutée avec succès.');

            if ($comic->isWishlist()) {
                return $this->redirectToRoute('app_wishlist');
            }

            return $this->redirectToRoute('app_home');
        }

        return $this->render('comic/new.html.twig', [
            'comic' => $comic,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_comic_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ComicSeries $comic, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ComicSeriesType::class, $comic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La série a été modifiée avec succès.');

            if ($comic->isWishlist()) {
                return $this->redirectToRoute('app_wishlist');
            }

            return $this->redirectToRoute('app_home');
        }

        return $this->render('comic/edit.html.twig', [
            'comic' => $comic,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_comic_delete', methods: ['POST'])]
    public function delete(Request $request, ComicSeries $comic, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $comic->getId(), $request->request->get('_token'))) {
            $wasWishlist = $comic->isWishlist();
            $entityManager->remove($comic);
            $entityManager->flush();

            $this->addFlash('success', 'La série a été supprimée avec succès.');

            if ($wasWishlist) {
                return $this->redirectToRoute('app_wishlist');
            }
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/{id}/to-library', name: 'app_comic_to_library', methods: ['POST'])]
    public function toLibrary(Request $request, ComicSeries $comic, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('to-library' . $comic->getId(), $request->request->get('_token'))) {
            $comic->setIsWishlist(false);
            $comic->setStatus(ComicStatus::BUYING);
            $entityManager->flush();

            $this->addFlash('success', 'La série a été déplacée vers la bibliothèque.');
        }

        return $this->redirectToRoute('app_home');
    }
}
