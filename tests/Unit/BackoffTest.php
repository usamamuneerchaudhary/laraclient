<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Usamamuneerchaudhary\LaraClient\Support\Backoff;

class BackoffTest extends TestCase
{
    #[Test]
    public function it_grows_exponentially_when_jitter_is_off(): void
    {
        $backoff = new Backoff(baseDelay: 100, multiplier: 2.0, maxDelay: 10_000, jitter: false);

        $this->assertSame(100, $backoff->delayFor(1));
        $this->assertSame(200, $backoff->delayFor(2));
        $this->assertSame(400, $backoff->delayFor(3));
    }

    #[Test]
    public function it_respects_the_ceiling(): void
    {
        $backoff = new Backoff(baseDelay: 1000, multiplier: 10.0, maxDelay: 5_000, jitter: false);

        $this->assertSame(5_000, $backoff->delayFor(4));
    }

    #[Test]
    public function jitter_keeps_delays_inside_the_window(): void
    {
        $backoff = new Backoff(baseDelay: 100, multiplier: 2.0, maxDelay: 10_000, jitter: true);

        for ($i = 0; $i < 50; $i++) {
            $this->assertLessThanOrEqual(400, $backoff->delayFor(3));
            $this->assertGreaterThanOrEqual(0, $backoff->delayFor(3));
        }
    }

    #[Test]
    public function it_reads_retry_after_as_seconds(): void
    {
        $this->assertSame(30, Backoff::parseRetryAfter('30'));
    }

    #[Test]
    public function it_reads_retry_after_as_an_http_date(): void
    {
        $future = gmdate('D, d M Y H:i:s \G\M\T', time() + 45);

        $this->assertEqualsWithDelta(45, Backoff::parseRetryAfter($future), 2);
    }

    #[Test]
    public function it_ignores_a_missing_or_nonsense_retry_after(): void
    {
        $this->assertNull(Backoff::parseRetryAfter(null));
        $this->assertNull(Backoff::parseRetryAfter('soon'));
    }
}
