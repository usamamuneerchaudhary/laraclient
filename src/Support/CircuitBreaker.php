<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Usamamuneerchaudhary\LaraClient\Events\CircuitClosed;
use Usamamuneerchaudhary\LaraClient\Events\CircuitOpened;

/**
 * Per-connection circuit breaker backed by the cache.
 *
 * Closed  -> requests flow, consecutive failures are counted.
 * Open    -> requests are rejected immediately for the cooldown period.
 * Half-open -> the first request after cooldown is allowed through as a probe.
 *              If it succeeds the circuit closes; if it fails the cooldown
 *              restarts, so a still-broken upstream is not hammered.
 */
class CircuitBreaker
{
    public function __construct(
        protected CacheRepository $cache,
        protected string $connection,
        protected int $threshold = 5,
        protected int $cooldown = 60,
    ) {}

    public function isOpen(): bool
    {
        return $this->retryAfter() > 0;
    }

    public function retryAfter(): int
    {
        $openedUntil = (int) $this->cache->get($this->key('open_until'), 0);

        return max(0, $openedUntil - time());
    }

    /**
     * True when the cooldown has elapsed but the circuit has not yet been
     * closed by a successful probe.
     */
    public function isHalfOpen(): bool
    {
        return $this->cache->get($this->key('open_until')) !== null
            && $this->retryAfter() === 0;
    }

    public function recordSuccess(): void
    {
        $wasTripped = $this->cache->get($this->key('open_until')) !== null;

        $this->cache->forget($this->key('failures'));
        $this->cache->forget($this->key('open_until'));

        if ($wasTripped) {
            event(new CircuitClosed($this->connection));
        }
    }

    public function recordFailure(): void
    {
        $failures = (int) $this->cache->get($this->key('failures'), 0) + 1;

        $this->cache->put($this->key('failures'), $failures, $this->cooldown * 2);

        if ($failures >= $this->threshold) {
            $this->trip($failures);
        }
    }

    protected function trip(int $failures): void
    {
        $this->cache->put(
            $this->key('open_until'),
            time() + $this->cooldown,
            $this->cooldown * 2,
        );

        event(new CircuitOpened($this->connection, $failures, $this->cooldown));
    }

    public function reset(): void
    {
        $this->cache->forget($this->key('failures'));
        $this->cache->forget($this->key('open_until'));
    }

    public function failures(): int
    {
        return (int) $this->cache->get($this->key('failures'), 0);
    }

    protected function key(string $suffix): string
    {
        return "laraclient:circuit:{$this->connection}:{$suffix}";
    }
}
