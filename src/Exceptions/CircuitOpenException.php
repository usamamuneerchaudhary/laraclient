<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Exceptions;

/**
 * The upstream failed repeatedly, so requests are being short-circuited rather
 * than piling more load onto a service that is already struggling.
 */
class CircuitOpenException extends LaraClientException
{
    public function __construct(
        public readonly int $retryAfter,
        ?string $connection = null,
    ) {
        parent::__construct(
            sprintf(
                'Circuit for connection [%s] is open after repeated failures. Retrying in %d second(s).',
                $connection ?? 'default',
                $retryAfter,
            ),
            $connection,
            503,
        );
    }
}
