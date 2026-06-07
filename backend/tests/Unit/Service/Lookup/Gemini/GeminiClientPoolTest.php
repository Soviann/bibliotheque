<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Gemini;

use App\Service\Lookup\Gemini\GeminiAllKeysExhaustedException;
use App\Service\Lookup\Gemini\GeminiClientPool;
use Gemini\Exceptions\ErrorException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests unitaires pour GeminiClientPool.
 */
final class GeminiClientPoolTest extends TestCase
{
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    /**
     * Teste qu'un appel simple avec une seule clé et un seul modèle fonctionne.
     */
    public function testSingleKeyAndModelSuccess(): void
    {
        $pool = new GeminiClientPool('key1', $this->logger, 'gemini-2.5-flash');

        $result = $pool->executeWithRetry(static function ($client, $model): string {
            self::assertSame('gemini-2.5-flash', $model);

            return 'ok';
        });

        self::assertSame('ok', $result);
    }

    /**
     * Teste la rotation vers la deuxième clé sur 429 de la première.
     */
    public function testRotatesToSecondKeyOn429(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'gemini-2.5-flash');

        $callCount = 0;
        $result = $pool->executeWithRetry(static function ($client, $model) use (&$callCount): string {
            ++$callCount;
            if (1 === $callCount) {
                throw new ErrorException(['code' => 429, 'message' => 'Resource exhausted', 'status' => 'RESOURCE_EXHAUSTED']);
            }

            return 'ok_key2';
        });

        self::assertSame('ok_key2', $result);
        self::assertSame(2, $callCount);
    }

    /**
     * Teste la dégradation vers le modèle suivant quand toutes les clés du premier sont épuisées.
     */
    public function testFallsToNextModelWhenAllKeysExhausted(): void
    {
        $pool = new GeminiClientPool('key1', $this->logger, 'gemini-2.5-flash,gemini-2.5-flash-lite');

        $callCount = 0;
        $modelsUsed = [];
        $result = $pool->executeWithRetry(static function ($client, $model) use (&$callCount, &$modelsUsed): string {
            ++$callCount;
            $modelsUsed[] = $model;
            if (1 === $callCount) {
                throw new ErrorException(['code' => 429, 'message' => 'Resource exhausted', 'status' => 'RESOURCE_EXHAUSTED']);
            }

            return 'ok_model2';
        });

        self::assertSame('ok_model2', $result);
        self::assertSame(['gemini-2.5-flash', 'gemini-2.5-flash-lite'], $modelsUsed);
    }

    /**
     * Teste que toutes les combinaisons en 429 lèvent une exception d'épuisement marquée quota.
     */
    public function testAllCombosExhaustedThrowsRateLimited(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'model1,model2');

        $caught = null;

        try {
            $pool->executeWithRetry($this->alwaysFailingCallback(429));
        } catch (GeminiAllKeysExhaustedException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(GeminiAllKeysExhaustedException::class, $caught);
        self::assertTrue($caught->rateLimited);
    }

    /**
     * Teste qu'un 500 (erreur serveur transitoire) déclenche une rotation, pas un abandon.
     */
    public function testRotatesToSecondKeyOn500(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'gemini-2.5-flash');

        $callCount = 0;
        $result = $pool->executeWithRetry(static function ($client, $model) use (&$callCount): string {
            ++$callCount;
            if (1 === $callCount) {
                throw new ErrorException(['code' => 500, 'message' => 'Internal error', 'status' => 'INTERNAL']);
            }

            return 'ok_key2';
        });

        self::assertSame('ok_key2', $result);
        self::assertSame(2, $callCount);
    }

    /**
     * Teste que des erreurs serveur seules (sans 429) ne sont pas marquées comme quota.
     */
    public function testAllCombos500ThrowsNotRateLimited(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'gemini-2.5-flash');

        $caught = null;

        try {
            $pool->executeWithRetry($this->alwaysFailingCallback(503));
        } catch (GeminiAllKeysExhaustedException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(GeminiAllKeysExhaustedException::class, $caught);
        self::assertFalse($caught->rateLimited);
    }

    /**
     * Teste la rotation vers la deuxième clé sur 403 (clé invalide) de la première.
     */
    public function testRotatesToSecondKeyOn403(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'gemini-2.5-flash');

        $callCount = 0;
        $result = $pool->executeWithRetry(static function ($client, $model) use (&$callCount): string {
            ++$callCount;
            if (1 === $callCount) {
                throw new ErrorException(['code' => 403, 'message' => 'API key invalid', 'status' => 'PERMISSION_DENIED']);
            }

            return 'ok_key2';
        });

        self::assertSame('ok_key2', $result);
        self::assertSame(2, $callCount);
    }

    /**
     * Teste la rotation vers la deuxième clé sur 401 (non authentifié) de la première.
     */
    public function testRotatesToSecondKeyOn401(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'gemini-2.5-flash');

        $callCount = 0;
        $result = $pool->executeWithRetry(static function ($client, $model) use (&$callCount): string {
            ++$callCount;
            if (1 === $callCount) {
                throw new ErrorException(['code' => 401, 'message' => 'Unauthenticated', 'status' => 'UNAUTHENTICATED']);
            }

            return 'ok_key2';
        });

        self::assertSame('ok_key2', $result);
        self::assertSame(2, $callCount);
    }

    /**
     * Teste la rotation vers la deuxième clé sur 400 (clé invalide/expirée) de la première.
     */
    public function testRotatesToSecondKeyOn400(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'gemini-2.5-flash');

        $callCount = 0;
        $result = $pool->executeWithRetry(static function ($client, $model) use (&$callCount): string {
            ++$callCount;
            if (1 === $callCount) {
                throw new ErrorException(['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT']);
            }

            return 'ok_key2';
        });

        self::assertSame('ok_key2', $result);
        self::assertSame(2, $callCount);
    }

    /**
     * Teste la rotation vers le modèle suivant sur 404 (modèle non trouvé).
     */
    public function testRotatesToNextModelOn404(): void
    {
        $pool = new GeminiClientPool('key1', $this->logger, 'invalid-model,gemini-2.5-flash');

        $callCount = 0;
        $modelsUsed = [];
        $result = $pool->executeWithRetry(static function ($client, $model) use (&$callCount, &$modelsUsed): string {
            ++$callCount;
            $modelsUsed[] = $model;
            if (1 === $callCount) {
                throw new ErrorException(['code' => 404, 'message' => 'Model not found', 'status' => 'NOT_FOUND']);
            }

            return 'ok_fallback';
        });

        self::assertSame('ok_fallback', $result);
        self::assertSame(['invalid-model', 'gemini-2.5-flash'], $modelsUsed);
    }

    /**
     * Teste qu'un code non retryable (hors liste) est relancé immédiatement sans rotation.
     */
    public function testNonRetryableErrorRethrowsImmediately(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'model1,model2');

        $callCount = 0;

        try {
            $pool->executeWithRetry(static function ($client, $model) use (&$callCount): never {
                ++$callCount;
                throw new ErrorException(['code' => 418, 'message' => 'Teapot', 'status' => 'UNKNOWN']);
            });
        } catch (ErrorException) {
            // Attendu
        }

        self::assertSame(1, $callCount);
    }

    /**
     * Teste qu'une clé vide lance une RuntimeException.
     */
    public function testEmptyKeysThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);

        new GeminiClientPool('', $this->logger, 'gemini-2.5-flash');
    }

    /**
     * Teste qu'une liste de modèles vide lance une RuntimeException.
     */
    public function testEmptyModelsThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);

        new GeminiClientPool('key1', $this->logger, '');
    }

    /**
     * Teste que les espaces autour des clés et modèles sont nettoyés.
     */
    public function testTrimsKeysAndModels(): void
    {
        $pool = new GeminiClientPool(' key1 , key2 ', $this->logger, ' model1 , model2 ');

        $modelsUsed = [];
        $pool->executeWithRetry(static function ($client, $model) use (&$modelsUsed): string {
            $modelsUsed[] = $model;

            return 'ok';
        });

        self::assertSame(['model1'], $modelsUsed);
    }

    /**
     * Teste que l'épuisement persiste au sein d'une même instance (batch).
     */
    public function testExhaustionPersistsAcrossCalls(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'gemini-2.5-flash');

        $callCount = 0;

        // Premier appel : key1 échoue en 429, key2 réussit
        $pool->executeWithRetry(static function ($client, $model) use (&$callCount): string {
            ++$callCount;
            if (1 === $callCount) {
                throw new ErrorException(['code' => 429, 'message' => 'Resource exhausted', 'status' => 'RESOURCE_EXHAUSTED']);
            }

            return 'ok';
        });

        // Deuxième appel : key1 est déjà marquée épuisée, on va directement à key2
        $keysUsedInSecondCall = [];
        $pool->executeWithRetry(static function ($client, $model) use (&$keysUsedInSecondCall): string {
            $keysUsedInSecondCall[] = 'called';

            return 'ok2';
        });

        // Un seul appel car key1 est déjà épuisée
        self::assertCount(1, $keysUsedInSecondCall);
    }

    /**
     * Crée un callback qui échoue toujours avec le code HTTP donné.
     *
     * Le type de retour déclaré (string) évite que PHPStan infère « never »
     * au site d'appel, ce qui marquerait à tort le code suivant comme mort.
     *
     * @return callable(\Gemini\Contracts\ClientContract, string): string
     */
    private function alwaysFailingCallback(int $code): callable
    {
        return static function ($client, $model) use ($code): string {
            throw new ErrorException(['code' => $code, 'message' => 'fail', 'status' => 'ERROR']);
        };
    }
}
