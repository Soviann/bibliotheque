<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Tests fonctionnels pour le endpoint POST /api/login/google.
 *
 * Note : les scénarios nécessitant la vérification d'un vrai token Google
 * (token valide, création d'utilisateur, email non autorisé) ne sont pas
 * testés ici car le Google\Client est un service externe difficile à mocker
 * dans un test fonctionnel. Seuls les chemins de validation de la requête
 * sont couverts. Les scénarios complets sont validés manuellement.
 *
 * Scénarios non testés (nécessiteraient un mock du Google\Client) :
 * - Token Google invalide -> 401
 * - Email non autorisé -> 403
 * - Login valide -> 200 avec JWT
 * - Création de l'utilisateur au premier login
 * - Retour de l'utilisateur existant au login suivant
 */
final class GoogleLoginTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;
    // ---------------------------------------------------------------
    // POST /api/login/google
    // ---------------------------------------------------------------

    public function testMissingCredentialReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login/google', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = $client->getResponse()->toArray(false);

        self::assertSame('Paramètre "credential" manquant.', $data['error']);
    }

    public function testMalformedBodyReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login/google', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['foo' => 'bar'],
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = $client->getResponse()->toArray(false);

        self::assertSame('Paramètre "credential" manquant.', $data['error']);
    }

    public function testEmptyBodyReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login/google', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '',
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testEndpointAcceptsPostOnly(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/login/google');

        self::assertResponseStatusCodeSame(405);
    }
}
