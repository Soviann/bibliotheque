<?php

declare(strict_types=1);

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests pour les headers de sécurité HTTP.
 */
class SecurityHeadersTest extends WebTestCase
{
    /**
     * Teste que la réponse contient le header X-Content-Type-Options.
     */
    public function testResponseHasXContentTypeOptionsHeader(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Teste que la réponse contient le header X-Frame-Options.
     */
    public function testResponseHasXFrameOptionsHeader(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    /**
     * Teste que la réponse contient le header Referrer-Policy.
     */
    public function testResponseHasReferrerPolicyHeader(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseHasHeader('Referrer-Policy');
    }

    /**
     * Teste que la réponse contient le header Content-Security-Policy.
     */
    public function testResponseHasContentSecurityPolicyHeader(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseHasHeader('Content-Security-Policy');
    }
}
