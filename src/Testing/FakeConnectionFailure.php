<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Testing;

/**
 * Marker for "this request never reaches the server", so retry backoff and
 * circuit-breaker behaviour can be exercised in a unit test.
 */
class FakeConnectionFailure
{
    public function __construct(
        public readonly string $message = 'Connection refused',
    ) {}
}
