<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ComicController;
use App\Dto\Input\ComicSeriesInput;
use App\Entity\ComicSeries;
use App\Service\ComicSeriesMapper;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Tests unitaires pour ComicController.
 *
 * Ces tests utilisent des mocks pour simuler les exceptions Doctrine
 * impossibles à reproduire en test fonctionnel.
 */
class ComicControllerUnitTest extends TestCase
{
    /**
     * Teste que new() affiche un message flash d'erreur si flush() lève une exception.
     */
    public function testNewActionShowsFlashErrorOnUniqueConstraintViolation(): void
    {
        // Création des mocks
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('flush')
            ->willThrowException($this->createUniqueConstraintException());

        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->stringContains('erreur'));

        $session = $this->createMock(Session::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        $request = new Request();
        $request->setSession($session);

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('createView')->willReturn($this->createMock(FormView::class));

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html></html>');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        // Mock du mapper
        $mapper = $this->createMock(ComicSeriesMapper::class);
        $mapper->method('mapToEntity')->willReturn(new ComicSeries());

        // Création du contrôleur avec le mapper mocké
        $controller = new ComicController($mapper);

        // Configuration du container
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => \in_array($id, ['form.factory', 'twig', 'router', 'request_stack'], true));
        $container->method('get')->willReturnCallback(
            static fn (string $id) => match ($id) {
                'form.factory' => $formFactory,
                'twig' => $twig,
                'router' => $urlGenerator,
                'request_stack' => $requestStack,
                default => null,
            }
        );

        $controller->setContainer($container);

        // Appel de la méthode
        $response = $controller->new($request, $entityManager);

        // Le contrôleur devrait réafficher le formulaire (pas de redirect, pas d'erreur 500)
        // avec un message flash d'erreur
        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * Teste que edit() affiche un message flash d'erreur si flush() lève une exception.
     */
    public function testEditActionShowsFlashErrorOnUniqueConstraintViolation(): void
    {
        $comic = new ComicSeries();
        $comic->setTitle('Test Series');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('flush')
            ->willThrowException($this->createUniqueConstraintException());

        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->stringContains('erreur'));

        $session = $this->createMock(Session::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        $request = new Request();
        $request->setSession($session);

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('createView')->willReturn($this->createMock(FormView::class));

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html></html>');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        // Mock du mapper
        $mapper = $this->createMock(ComicSeriesMapper::class);
        $mapper->method('mapToInput')->willReturn(new ComicSeriesInput());
        $mapper->method('mapToEntity')->willReturn($comic);

        $controller = new ComicController($mapper);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => \in_array($id, ['form.factory', 'twig', 'router', 'request_stack'], true));
        $container->method('get')->willReturnCallback(
            static fn (string $id) => match ($id) {
                'form.factory' => $formFactory,
                'twig' => $twig,
                'router' => $urlGenerator,
                'request_stack' => $requestStack,
                default => null,
            }
        );

        $controller->setContainer($container);

        $response = $controller->edit($request, $comic, $entityManager);

        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * Crée une instance de UniqueConstraintViolationException pour les tests.
     */
    private function createUniqueConstraintException(): UniqueConstraintViolationException
    {
        // Utilisation d'une classe anonyme car l'interface Driver\Exception étend Throwable
        $driverException = new class('Duplicate entry', 1062) extends \Exception implements DriverExceptionInterface {
            public function getSQLState(): string
            {
                return '23000';
            }
        };

        return new UniqueConstraintViolationException($driverException, null);
    }
}
