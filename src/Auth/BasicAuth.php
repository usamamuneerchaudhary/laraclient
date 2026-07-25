<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Auth;

use Psr\Http\Message\RequestInterface;
use Usamamuneerchaudhary\LaraClient\Contracts\AuthStrategy;

class BasicAuth implements AuthStrategy
{
    public function __construct(
        protected ?string $username,
        protected ?string $password,
    ) {}

    public function apply(RequestInterface $request): RequestInterface
    {
        if (blank($this->username)) {
            return $request;
        }

        return $request->withHeader(
            'Authorization',
            'Basic '.base64_encode($this->username.':'.($this->password ?? '')),
        );
    }

    public function refresh(): bool
    {
        return false;
    }
}
