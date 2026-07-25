<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Auth;

use Psr\Http\Message\RequestInterface;
use Usamamuneerchaudhary\LaraClient\Contracts\AuthStrategy;

class BearerAuth implements AuthStrategy
{
    public function __construct(
        protected ?string $token,
        protected string $prefix = 'Bearer',
    ) {}

    public function apply(RequestInterface $request): RequestInterface
    {
        if (blank($this->token)) {
            return $request;
        }

        return $request->withHeader('Authorization', trim($this->prefix.' '.$this->token));
    }

    public function refresh(): bool
    {
        return false;
    }
}
