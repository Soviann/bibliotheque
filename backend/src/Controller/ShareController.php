<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Share\ShareResolver;
use App\Service\Share\ShareUrlParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoint de réception des liens partagés via Web Share Target.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/share')]
final readonly class ShareController
{
    public function __construct(
        private ShareResolver $shareResolver,
        private ShareUrlParser $shareUrlParser,
    ) {
    }

    /**
     * Reçoit une URL partagée, résout la série correspondante et retourne le résultat.
     */
    #[Route('', name: 'api_share', methods: ['POST'])]
    public function share(Request $request): JsonResponse
    {
        $payload = \json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'url is required'], Response::HTTP_BAD_REQUEST);
        }

        $url = \is_string($payload['url'] ?? null) ? $payload['url'] : '';

        if ('' === $url) {
            return new JsonResponse(['error' => 'url is required'], Response::HTTP_BAD_REQUEST);
        }

        $info = $this->shareUrlParser->parse($url);
        $titleFallback = \is_string($payload['title'] ?? null) ? $payload['title'] : null;
        $resolution = $this->shareResolver->resolve($info, $titleFallback);

        if ($resolution->matched) {
            return new JsonResponse(['matched' => true, 'seriesId' => $resolution->seriesId]);
        }

        if (null !== $resolution->lookupResult) {
            return new JsonResponse(['matched' => false, 'lookupResult' => $resolution->lookupResult->jsonSerialize()]);
        }

        return new JsonResponse(['error' => 'no data found'], Response::HTTP_NOT_FOUND);
    }
}
