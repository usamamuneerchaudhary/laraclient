<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Support;

/**
 * Exponential backoff with full jitter.
 *
 * Without jitter, every client that failed at the same moment retries at the
 * same moment, and the recovering upstream gets hit by a synchronised wave.
 * Full jitter spreads each client randomly across its own backoff window.
 */
class Backoff
{
    public function __construct(
        protected int $baseDelay = 200,
        protected float $multiplier = 2.0,
        protected int $maxDelay = 10_000,
        protected bool $jitter = true,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            (int) ($config['base_delay'] ?? 200),
            (float) ($config['multiplier'] ?? 2.0),
            (int) ($config['max_delay'] ?? 10_000),
            (bool) ($config['jitter'] ?? true),
        );
    }

    /**
     * Delay in milliseconds before the given attempt (1-indexed).
     */
    public function delayFor(int $attempt): int
    {
        $exponential = (int) min(
            $this->maxDelay,
            $this->baseDelay * ($this->multiplier ** max(0, $attempt - 1)),
        );

        if (! $this->jitter) {
            return $exponential;
        }

        return random_int(0, max(0, $exponential));
    }

    /**
     * Honor a Retry-After header, which may be either a delay in seconds or an
     * HTTP date. Providers use both, and guessing wrong means retrying early.
     */
    public static function parseRetryAfter(?string $header): ?int
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $header = trim($header);

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}
