<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Vérifie le rate limit pour la requête courante.
 */
trait RateLimitTrait
{
    private function checkRateLimit(Request $request, RateLimiterFactory $limiterFactory): ?JsonResponse
    {
        $limit = $limiterFactory->create($request->getClientIp() ?? 'unknown')->consume();

        if (false === $limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - \time();

            $response = new JsonResponse(
                ['error' => 'Trop de requêtes. Réessayez plus tard.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
            $response->headers->set('Retry-After', (string) \max(1, $retryAfter));

            return $response;
        }

        return null;
    }
}
