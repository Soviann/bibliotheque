<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gère le cache HTTP et les ETags pour les endpoints ComicSeries.
 *
 * Pour la collection (/api/comic_series), utilise un ETag versionné instantané
 * permettant de répondre 304 dès kernel.request sans exécuter de requêtes SQL
 * ni de sérialisation.
 *
 * Pour les ressources individuelles (/api/comic_series/{id}), calcule l'ETag
 * à partir du contenu de la réponse sur kernel.response.
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 0)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
final readonly class HttpCacheListener
{
    private const string CACHE_PATH_COLLECTION = '/api/comic_series';
    private const string CACHE_PATH_PREFIX = '/api/comic_series';

    public function __construct(
        private ComicSeriesCacheInvalidator $cacheInvalidator,
    ) {
    }

    /**
     * Court-circuit 304 instantané pour la collection sur If-None-Match valide.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ('GET' !== $request->getMethod()) {
            return;
        }

        if (self::CACHE_PATH_COLLECTION !== $request->getPathInfo() || [] !== $request->query->all()) {
            return;
        }

        $etag = $this->getCollectionEtag();
        $cleanEtag = (string) \preg_replace('/^W\//', '', $etag);
        $ifNoneMatch = $request->headers->get('If-None-Match');

        if (null !== $ifNoneMatch && '' !== $ifNoneMatch) {
            $clientEtags = \array_map(
                static fn (string $tag): string => (string) \preg_replace('/^W\//', '', \trim($tag)),
                \explode(',', $ifNoneMatch),
            );
            if (\in_array($cleanEtag, $clientEtags, true) || \in_array('*', $clientEtags, true)) {
                $response = new Response('', Response::HTTP_NOT_MODIFIED, [
                    'Cache-Control' => 'no-cache, private',
                    'ETag' => $etag,
                ]);
                $event->setResponse($response);
            }
        }
    }

    /**
     * Positionne l'ETag et les en-têtes de cache sur la réponse.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if ('GET' !== $request->getMethod()) {
            return;
        }

        if (!\str_starts_with($request->getPathInfo(), self::CACHE_PATH_PREFIX)) {
            return;
        }

        if (!$response->isSuccessful()) {
            return;
        }

        if (self::CACHE_PATH_COLLECTION === $request->getPathInfo() && [] === $request->query->all()) {
            $etag = $this->getCollectionEtag();
        } else {
            $etag = \md5((string) $response->getContent());
        }

        $response->setEtag($etag);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');

        $response->isNotModified($request);
    }

    private function getCollectionEtag(): string
    {
        return \sprintf('"%s"', \md5('comic_series_collection_'.$this->cacheInvalidator->getVersion()));
    }
}
