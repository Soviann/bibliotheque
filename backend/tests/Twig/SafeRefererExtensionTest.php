<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\SafeRefererExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests pour SafeRefererExtension.
 */
class SafeRefererExtensionTest extends TestCase
{
    /**
     * Teste qu'un referer du même host est accepté.
     */
    public function testSafeRefererAcceptsSameHost(): void
    {
        $request = Request::create('https://bibliotheque.ddev.site/comic/1');
        $request->headers->set('referer', 'https://bibliotheque.ddev.site/wishlist');

        $extension = $this->createExtension($request);

        self::assertSame('https://bibliotheque.ddev.site/wishlist', $extension->safeReferer('/'));
    }

    /**
     * Teste qu'un referer du même host avec port est accepté.
     */
    public function testSafeRefererAcceptsSameHostWithPort(): void
    {
        $request = Request::create('https://localhost:8443/comic/1');
        $request->headers->set('referer', 'https://localhost:8443/wishlist');

        $extension = $this->createExtension($request);

        self::assertSame('https://localhost:8443/wishlist', $extension->safeReferer('/'));
    }

    /**
     * Teste qu'un referer d'un host différent est rejeté.
     */
    public function testSafeRefererRejectsDifferentHost(): void
    {
        $request = Request::create('https://bibliotheque.ddev.site/comic/1');
        $request->headers->set('referer', 'https://evil.com/malicious');

        $extension = $this->createExtension($request);

        self::assertSame('/', $extension->safeReferer('/'));
    }

    /**
     * Teste qu'un referer avec sous-domaine différent est rejeté.
     */
    public function testSafeRefererRejectsDifferentSubdomain(): void
    {
        $request = Request::create('https://bibliotheque.ddev.site/comic/1');
        $request->headers->set('referer', 'https://other.ddev.site/malicious');

        $extension = $this->createExtension($request);

        self::assertSame('/fallback', $extension->safeReferer('/fallback'));
    }

    /**
     * Teste qu'un referer vide retourne le fallback.
     */
    public function testSafeRefererReturnsDefaultWhenEmpty(): void
    {
        $request = Request::create('https://bibliotheque.ddev.site/comic/1');

        $extension = $this->createExtension($request);

        self::assertSame('/home', $extension->safeReferer('/home'));
    }

    /**
     * Teste qu'un referer null retourne le fallback.
     */
    public function testSafeRefererReturnsDefaultWhenNull(): void
    {
        $request = Request::create('https://bibliotheque.ddev.site/comic/1');
        $request->headers->remove('referer');

        $extension = $this->createExtension($request);

        self::assertSame('/', $extension->safeReferer('/'));
    }

    /**
     * Teste qu'un referer invalide (pas une URL) retourne le fallback.
     */
    public function testSafeRefererReturnsDefaultForInvalidUrl(): void
    {
        $request = Request::create('https://bibliotheque.ddev.site/comic/1');
        $request->headers->set('referer', 'not-a-valid-url');

        $extension = $this->createExtension($request);

        self::assertSame('/fallback', $extension->safeReferer('/fallback'));
    }

    /**
     * Teste qu'un referer avec protocole différent (http vs https) du même host est accepté.
     */
    public function testSafeRefererAcceptsDifferentProtocol(): void
    {
        $request = Request::create('https://bibliotheque.ddev.site/comic/1');
        $request->headers->set('referer', 'http://bibliotheque.ddev.site/wishlist');

        $extension = $this->createExtension($request);

        // Même host, protocole différent devrait être accepté
        self::assertSame('http://bibliotheque.ddev.site/wishlist', $extension->safeReferer('/'));
    }

    /**
     * Teste sans requête active.
     */
    public function testSafeRefererReturnsDefaultWithoutRequest(): void
    {
        $extension = $this->createExtension(null);

        self::assertSame('/fallback', $extension->safeReferer('/fallback'));
    }

    private function createExtension(?Request $request): SafeRefererExtension
    {
        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        return new SafeRefererExtension($requestStack);
    }
}
