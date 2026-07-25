<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Contracts;

use Psr\Http\Message\RequestInterface;

interface AuthStrategy
{
    /**
     * Returns the request with credentials applied.
     */
    public function apply(RequestInterface $request): RequestInterface;

    /**
     * Called after a 401 so the strategy can invalidate anything it cached.
     * Returns true if it has something new to try, which triggers one retry.
     */
    public function refresh(): bool;
}
