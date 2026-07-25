<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Fixed-window client-side throttle, scoped per connection.
 */
class RateLimiter
{
    public function __construct(
        protected CacheRepository $cache,
        protected string $connection,
        protected int $limit = 60,
        protected int $window = 60,
    ) {}

    /**
     * Consumes a slot. Returns true if the request may proceed.
     *
     * @phpstan-impure
     */
    public function attempt(): bool
    {
        $key = $this->windowKey();
        $used = (int) $this->cache->get($key, 0);

        if ($used >= $this->limit) {
            return false;
        }

        if (! $this->cache->add($key, 1, $this->window)) {
            $this->cache->increment($key);
        }

        return true;
    }

    public function remaining(): int
    {
        return max(0, $this->limit - (int) $this->cache->get($this->windowKey(), 0));
    }

    /**
     * Seconds until the current window rolls over.
     */
    public function availableIn(): int
    {
        $elapsed = time() % $this->window;

        return max(1, $this->window - $elapsed);
    }

    /**
     * Records a limit advertised by the provider itself (429 or X-RateLimit-*),
     * which is more authoritative than our local count.
     */
    public function blockFor(int $seconds): void
    {
        $this->cache->put($this->windowKey(), $this->limit, max(1, $seconds));
    }

    public function clear(): void
    {
        $this->cache->forget($this->windowKey());
    }

    protected function windowKey(): string
    {
        $bucket = (int) floor(time() / $this->window);

        return "laraclient:ratelimit:{$this->connection}:{$bucket}";
    }
}
