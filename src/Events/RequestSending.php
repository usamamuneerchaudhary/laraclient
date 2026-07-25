<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Events;

use Psr\Http\Message\RequestInterface;

class RequestSending
{
    public function __construct(
        public readonly string $connection,
        public readonly RequestInterface $request,
        public readonly int $attempt = 1,
    ) {}
}
