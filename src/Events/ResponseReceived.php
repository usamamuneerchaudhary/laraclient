<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Events;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseReceived
{
    public function __construct(
        public readonly string $connection,
        public readonly RequestInterface $request,
        public readonly ResponseInterface $response,
        public readonly float $durationMs,
        public readonly bool $fromCache = false,
    ) {}
}
