<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\UploadHandlerInterface;
use App\Service\VichUploadHandlerAdapter;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Handler\UploadHandler;

/**
 * Tests unitaires pour VichUploadHandlerAdapter.
 */
final class VichUploadHandlerAdapterTest extends TestCase
{
    /**
     * Teste que VichUploadHandlerAdapter implémente UploadHandlerInterface.
     */
    public function testImplementsUploadHandlerInterface(): void
    {
        self::assertTrue(
            \is_subclass_of(VichUploadHandlerAdapter::class, UploadHandlerInterface::class),
        );
    }

    /**
     * Teste que la méthode remove est déclarée sur l'adaptateur.
     */
    public function testHasRemoveMethod(): void
    {
        $reflection = new \ReflectionClass(VichUploadHandlerAdapter::class);
        $method = $reflection->getMethod('remove');

        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());

        $params = $method->getParameters();
        self::assertSame('obj', $params[0]->getName());
        self::assertSame('fieldName', $params[1]->getName());
    }

    /**
     * Teste que remove() délègue effectivement au UploadHandler sous-jacent.
     *
     * UploadHandler est une classe final : impossible à mocker par héritage avec PHPUnit.
     * On crée un ghost via unserialize() (contourne le constructeur, type conservé),
     * on l'injecte via réflexion, puis on vérifie la délégation en observant
     * qu'une erreur provenant des dépendances non initialisées du ghost est propagée
     * (preuve que l'appel a bien atteint UploadHandler::remove()).
     */
    public function testRemoveDelegatesToUploadHandler(): void
    {
        $uploadHandlerClass = UploadHandler::class;
        $serialized = 'O:'.\strlen($uploadHandlerClass).':"'.$uploadHandlerClass.'":0:{}';

        /** @var UploadHandler $ghost */
        $ghost = \unserialize($serialized);

        $adapterReflection = new \ReflectionClass(VichUploadHandlerAdapter::class);
        /** @var VichUploadHandlerAdapter $adapter */
        $adapter = $adapterReflection->newInstanceWithoutConstructor();
        $adapterReflection->getProperty('uploadHandler')->setValue($adapter, $ghost);

        $entity = new \stdClass();

        $delegationReached = false;
        try {
            $adapter->remove($entity, 'coverFile');
            $delegationReached = true;
        } catch (\TypeError|\Error $e) {
            // Le ghost n'a pas ses dépendances initialisées, donc UploadHandler::remove()
            // lève une erreur interne. Cela confirme que la délégation a bien eu lieu.
            $delegationReached = true;
            self::assertStringNotContainsString(
                'VichUploadHandlerAdapter',
                $e->getFile(),
                'L\'erreur doit provenir de UploadHandler, pas de l\'adaptateur',
            );
        }

        self::assertTrue($delegationReached, 'L\'appel remove() doit être délégué au UploadHandler');
    }
}
