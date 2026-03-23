<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute un ETag (hash du contenu) aux réponses GET des endpoints ComicSeries.
 * Retourne 304 Not Modified si le client envoie un If-None-Match valide.
 *
 * Note : la réponse complète est calculée côté serveur avant le hash.
 * L'optimisation porte sur la bande passante (pas de retransfert si inchangé).
 */
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
final class HttpCacheListener
{
    private const string CACHE_PATH_PREFIX = '/api/comic_series';

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

        $etag = \md5((string) $response->getContent());
        $response->setEtag($etag);
        $response->setPrivate();
        $response->setMaxAge(300);
        $response->headers->addCacheControlDirective('must-revalidate');

        $response->isNotModified($request);
    }
}
