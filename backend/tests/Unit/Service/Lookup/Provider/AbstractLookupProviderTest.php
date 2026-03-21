<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Provider;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use App\Enum\LookupMode;
use App\Service\Lookup\Provider\AbstractLookupProvider;
use App\Service\Lookup\Contract\ApiMessage;
use App\Service\Lookup\Contract\LookupResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Sous-classe concrete pour tester AbstractLookupProvider.
 */
final class ConcreteTestProvider extends AbstractLookupProvider
{
    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        return 50;
    }

    public function getName(): string
    {
        return 'test';
    }

    public function prepareLookup(string $query, ?ComicType $type, LookupMode $mode = LookupMode::TITLE): mixed
    {
        return null;
    }

    /**
     * Expose recordApiMessage pour les tests.
     */
    public function publicRecordApiMessage(ApiLookupStatus $status, string $message): void
    {
        $this->recordApiMessage($status, $message);
    }

    /**
     * Expose resetApiMessage pour les tests.
     */
    public function publicResetApiMessage(): void
    {
        $this->resetApiMessage();
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        return null;
    }

    public function supports(LookupMode $mode, ?ComicType $type): bool
    {
        return true;
    }

    protected function getLogger(): LoggerInterface
    {
        return new NullLogger();
    }
}

/**
 * Tests unitaires pour AbstractLookupProvider.
 */
final class AbstractLookupProviderTest extends TestCase
{
    private ConcreteTestProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ConcreteTestProvider();
    }

    /**
     * Teste que lastApiMessage est null par defaut.
     */
    public function testInitialLastApiMessageIsNull(): void
    {
        self::assertNull($this->provider->getLastApiMessage());
    }

    /**
     * Teste que recordApiMessage enregistre le message.
     */
    public function testRecordApiMessageSetsMessage(): void
    {
        $this->provider->publicRecordApiMessage(ApiLookupStatus::SUCCESS, 'Donnees trouvees');

        $message = $this->provider->getLastApiMessage();

        self::assertInstanceOf(ApiMessage::class, $message);
        self::assertSame('success', $message->status);
        self::assertSame('Donnees trouvees', $message->message);
    }

    /**
     * Teste que getLastApiMessage retourne le message enregistre.
     */
    public function testGetLastApiMessageReturnsRecordedMessage(): void
    {
        $this->provider->publicRecordApiMessage(ApiLookupStatus::ERROR, 'Erreur reseau');

        $message = $this->provider->getLastApiMessage();

        self::assertInstanceOf(ApiMessage::class, $message);
        self::assertSame('error', $message->status);
        self::assertSame('Erreur reseau', $message->message);
    }

    /**
     * Teste que resetApiMessage remet le message a null.
     */
    public function testResetApiMessageClearsMessage(): void
    {
        $this->provider->publicRecordApiMessage(ApiLookupStatus::SUCCESS, 'OK');
        self::assertNotNull($this->provider->getLastApiMessage());

        $this->provider->publicResetApiMessage();

        self::assertNull($this->provider->getLastApiMessage());
    }

    /**
     * Teste que plusieurs appels a recordApiMessage : le dernier gagne.
     */
    public function testMultipleRecordsLastOneWins(): void
    {
        $this->provider->publicRecordApiMessage(ApiLookupStatus::SUCCESS, 'Premier');
        $this->provider->publicRecordApiMessage(ApiLookupStatus::NOT_FOUND, 'Deuxieme');
        $this->provider->publicRecordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Troisieme');

        $message = $this->provider->getLastApiMessage();

        self::assertSame('rate_limited', $message->status);
        self::assertSame('Troisieme', $message->message);
    }
}
