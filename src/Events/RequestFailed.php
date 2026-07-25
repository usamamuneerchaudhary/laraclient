<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Events;

use Psr\Http\Message\RequestInterface;
use Throwable;

class RequestFailed
{
    public function __construct(
        public readonly string $connection,
        public readonly RequestInterface $request,
        public readonly Throwable $exception,
        public readonly float $durationMs,
    ) {}
}
