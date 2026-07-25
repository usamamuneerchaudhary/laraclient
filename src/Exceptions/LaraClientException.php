<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Exceptions;

use RuntimeException;

class LaraClientException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $connection = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
