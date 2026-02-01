<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig pour sécuriser l'utilisation du header Referer.
 *
 * Protège contre les attaques Open Redirect en validant que le referer
 * appartient au même host que l'application.
 */
class SafeRefererExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('safe_referer', $this->safeReferer(...)),
        ];
    }

    /**
     * Retourne le referer s'il appartient au même host, sinon le fallback.
     *
     * @param string $fallback URL de fallback si le referer est invalide ou absent
     */
    public function safeReferer(string $fallback): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return $fallback;
        }

        $referer = $request->headers->get('referer');
        if (null === $referer || '' === $referer) {
            return $fallback;
        }

        // Parse les URLs
        $refererParts = \parse_url($referer);
        if (false === $refererParts || !isset($refererParts['host'])) {
            return $fallback;
        }

        // Compare les hosts (incluant le port si présent)
        $currentHost = $request->getHost();
        $refererHost = $refererParts['host'];

        if ($currentHost !== $refererHost) {
            return $fallback;
        }

        return $referer;
    }
}
