<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Auth;

use Psr\Http\Message\RequestInterface;
use Usamamuneerchaudhary\LaraClient\Contracts\AuthStrategy;

/**
 * Key as a query parameter (?api_key=...). Add the parameter name to
 * redact.body so it is masked in the logs.
 */
class QueryAuth implements AuthStrategy
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

        $uri = $request->getUri();
        parse_str($uri->getQuery(), $query);
        $query[$this->name] = $this->value;

        return $request->withUri($uri->withQuery(http_build_query($query)));
    }

    public function refresh(): bool
    {
        return false;
    }
}
