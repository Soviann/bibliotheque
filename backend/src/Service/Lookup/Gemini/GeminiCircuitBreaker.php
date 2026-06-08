<?php

declare(strict_types=1);

namespace App\Service\Lookup\Gemini;

use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Disjoncteur pour l'API Gemini : ouvert lorsque toutes les clés sont en quota.
 *
 * L'état est stocké dans un pool de cache partagé entre les conteneurs php et
 * worker (volume `app_var`). La limite quotidienne (RPD) Gemini se réinitialise
 * à minuit heure du Pacifique : le disjoncteur reste donc ouvert jusqu'à ce
 * prochain reset, ce qui évite de marteler l'API et de gâcher du quota.
 */
class GeminiCircuitBreaker
{
    private const string CACHE_KEY = 'gemini_circuit_breaker_open_until';
    private const string RESET_TIMEZONE = 'America/Los_Angeles';

    public function __construct(
        #[Autowire(service: 'gemini.cache')]
        private readonly AdapterInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Ferme le disjoncteur (réautorise les appels Gemini).
     */
    public function close(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    /**
     * Indique si le disjoncteur est actuellement ouvert.
     */
    public function isOpen(): bool
    {
        return null !== $this->openUntil();
    }

    /**
     * Ouvre le disjoncteur jusqu'au prochain reset quotidien Gemini.
     *
     * @return \DateTimeImmutable Instant de réouverture
     */
    public function open(): \DateTimeImmutable
    {
        $until = $this->nextDailyReset();

        // TTL relatif (et non `expiresAt($until)` absolu) : l'expiration du cache
        // suit l'horloge réelle au moment du save, tandis que la décision
        // ouvert/fermé reste pilotée par l'horloge injectée (cf. openUntil).
        $ttl = \max(1, $until->getTimestamp() - $this->clock->now()->getTimestamp());

        $item = $this->cache->getItem(self::CACHE_KEY);
        $item->set($until->getTimestamp());
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        return $until;
    }

    /**
     * Instant de réouverture si le disjoncteur est ouvert, sinon null.
     */
    public function openUntil(): ?\DateTimeImmutable
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        if (!\is_int($value)) {
            return null;
        }

        $until = $this->clock->now()->setTimestamp($value);

        return $this->clock->now() < $until ? $until : null;
    }

    /**
     * Nombre de secondes avant réouverture (0 si fermé).
     */
    public function retryAfterSeconds(): int
    {
        $until = $this->openUntil();

        if (null === $until) {
            return 0;
        }

        return \max(0, $until->getTimestamp() - $this->clock->now()->getTimestamp());
    }

    /**
     * Calcule le prochain reset quotidien Gemini (minuit, heure du Pacifique).
     */
    private function nextDailyReset(): \DateTimeImmutable
    {
        $pacific = $this->clock->now()->setTimezone(new \DateTimeZone(self::RESET_TIMEZONE));
        $nextMidnight = $pacific->setTime(0, 0)->modify('+1 day');

        return $nextMidnight->setTimezone(new \DateTimeZone('UTC'));
    }
}
