<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\HttpCacheListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Tests unitaires pour HttpCacheListener.
 */
final class HttpCacheListenerTest extends TestCase
{
    private HttpCacheListener $listener;
    private KernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createStub(KernelInterface::class);
        $this->listener = new HttpCacheListener();
    }

    public function testAddsEtagToGetCollectionResponse(): void
    {
        $request = Request::create('/api/comic_series', Request::METHOD_GET);
        $response = new Response('{"member":[]}', Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertNotNull($response->getEtag(), 'La réponse doit contenir un ETag');
    }

    public function testAddsEtagToGetItemResponse(): void
    {
        $request = Request::create('/api/comic_series/42', Request::METHOD_GET);
        $response = new Response('{"title":"Test"}', Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertNotNull($response->getEtag(), 'La réponse doit contenir un ETag');
    }

    public function testReturns304WhenEtagMatches(): void
    {
        $content = '{"member":[]}';
        $etag = '"'.\md5($content).'"';

        $request = Request::create('/api/comic_series', Request::METHOD_GET, server: ['HTTP_IF_NONE_MATCH' => $etag]);
        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertSame(304, $response->getStatusCode(), 'Doit répondre 304 quand l\'ETag correspond');
    }

    public function testReturns200WhenEtagDoesNotMatch(): void
    {
        $request = Request::create('/api/comic_series', Request::METHOD_GET, server: ['HTTP_IF_NONE_MATCH' => '"stale-etag"']);
        $response = new Response('{"member":[{"title":"Nouveau"}]}', Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertSame(200, $response->getStatusCode(), 'Doit répondre 200 quand l\'ETag ne correspond pas');
        self::assertNotNull($response->getEtag(), 'La réponse doit contenir un nouveau ETag');
    }

    public function testIgnoresPostRequests(): void
    {
        $request = Request::create('/api/comic_series', Request::METHOD_POST);
        $response = new Response('{"title":"Created"}', Response::HTTP_CREATED);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertNull($response->getEtag(), 'Ne doit pas ajouter d\'ETag aux requêtes POST');
    }

    public function testIgnoresNonApiRoutes(): void
    {
        $request = Request::create('/api/authors', Request::METHOD_GET);
        $response = new Response('[]', Response::HTTP_OK);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertNull($response->getEtag(), 'Ne doit pas ajouter d\'ETag aux routes non ciblées');
    }

    public function testIgnoresSubRequests(): void
    {
        $request = Request::create('/api/comic_series', Request::METHOD_GET);
        $response = new Response('{}', Response::HTTP_OK);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertNull($response->getEtag(), 'Ne doit pas traiter les sous-requêtes');
    }

    public function testIgnoresErrorResponses(): void
    {
        $request = Request::create('/api/comic_series', Request::METHOD_GET);
        $response = new Response('{"error":"Not found"}', Response::HTTP_NOT_FOUND);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertNull($response->getEtag(), 'Ne doit pas ajouter d\'ETag aux réponses d\'erreur');
    }

    public function testSetsNoCacheOnSuccessfulGetResponse(): void
    {
        $request = Request::create('/api/comic_series', Request::METHOD_GET);
        $response = new Response('{"member":[]}', Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertTrue($response->headers->hasCacheControlDirective('no-cache'), 'Doit forcer la revalidation à chaque requête');
        self::assertNull($response->headers->getCacheControlDirective('max-age'), 'Ne doit pas définir max-age (qui ignorerait no-cache)');
    }

    public function testDoesNotProcessNonApiRoutes(): void
    {
        $request = Request::create('/api/authors', Request::METHOD_GET);
        $response = new Response('[]', Response::HTTP_OK);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        self::assertNull($response->getEtag(), 'Ne doit pas ajouter d\'ETag sur les routes non ciblées');
    }

    public function testEtagChangesWithDifferentContent(): void
    {
        $request1 = Request::create('/api/comic_series', Request::METHOD_GET);
        $response1 = new Response('{"member":[]}', Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
        $event1 = new ResponseEvent($this->kernel, $request1, HttpKernelInterface::MAIN_REQUEST, $response1);

        $request2 = Request::create('/api/comic_series', Request::METHOD_GET);
        $response2 = new Response('{"member":[{"title":"New"}]}', Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
        $event2 = new ResponseEvent($this->kernel, $request2, HttpKernelInterface::MAIN_REQUEST, $response2);

        $this->listener->onKernelResponse($event1);
        $this->listener->onKernelResponse($event2);

        self::assertNotSame($response1->getEtag(), $response2->getEtag(), 'L\'ETag doit changer quand le contenu change');
    }
}
