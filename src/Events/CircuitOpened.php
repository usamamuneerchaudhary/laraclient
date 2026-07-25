<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Events;

class CircuitOpened
{
    public function __construct(
        public readonly string $connection,
        public readonly int $failures,
        public readonly int $cooldown,
    ) {}
}
