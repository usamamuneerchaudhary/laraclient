<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Events;

class CircuitClosed
{
    public function __construct(
        public readonly string $connection,
    ) {}
}
