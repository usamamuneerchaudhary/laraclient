<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Auth;

use Psr\Http\Message\RequestInterface;
use Usamamuneerchaudhary\LaraClient\Contracts\AuthStrategy;

class NullAuth implements AuthStrategy
{
    public function apply(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    public function refresh(): bool
    {
        return false;
    }
}
