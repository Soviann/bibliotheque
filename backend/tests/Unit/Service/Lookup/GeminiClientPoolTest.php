<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Service\Lookup\GeminiClientPool;
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
        $pool = new GeminiClientPool('key1', $this->logger, 'gemini-2.5-flash,gemini-3-flash');

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
        self::assertSame(['gemini-2.5-flash', 'gemini-3-flash'], $modelsUsed);
    }

    /**
     * Teste que toutes les combinaisons épuisées relancent la dernière ErrorException 429.
     */
    public function testAllCombosExhaustedThrows429(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'model1,model2');

        $this->expectException(ErrorException::class);

        $pool->executeWithRetry(static function ($client, $model): never {
            throw new ErrorException(['code' => 429, 'message' => 'Resource exhausted', 'status' => 'RESOURCE_EXHAUSTED']);
        });
    }

    /**
     * Teste qu'une erreur non-429 est relancée immédiatement sans rotation.
     */
    public function testNon429ErrorRethrowsImmediately(): void
    {
        $pool = new GeminiClientPool('key1,key2', $this->logger, 'model1,model2');

        $callCount = 0;

        try {
            $pool->executeWithRetry(static function ($client, $model) use (&$callCount): never {
                ++$callCount;
                throw new ErrorException(['code' => 500, 'message' => 'Internal error', 'status' => 'INTERNAL']);
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
}
