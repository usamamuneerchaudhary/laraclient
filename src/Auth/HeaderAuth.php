<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Auth;

use Psr\Http\Message\RequestInterface;
use Usamamuneerchaudhary\LaraClient\Contracts\AuthStrategy;

class HeaderAuth implements AuthStrategy
{
    public function __construct(
        protected string $name,
        protected ?string $value,
    ) {}

    public function apply(RequestInterface $request): RequestInterface
    {
        if (blank($this->value)) {
            return $request;
        }

        return $request->withHeader($this->name, $this->value);
    }

    public function refresh(): bool
    {
        return false;
    }
}
