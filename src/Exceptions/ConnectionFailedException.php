<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Exceptions;

use Throwable;

/**
 * DNS failures, refused connections and timeouts.
 */
class ConnectionFailedException extends LaraClientException
{
    public function __construct(
        string $message,
        public readonly string $url = '',
        ?string $connection = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $connection, 0, $previous);
    }
}
