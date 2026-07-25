<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Usamamuneerchaudhary\LaraClient\Contracts\AuthStrategy;

/**
 * Applies credentials as late as possible, so a retry picks up a token that
 * was refreshed after the first attempt failed.
 *
 * On a 401 the strategy is asked to refresh once. For OAuth2 that swaps an
 * expired token for a fresh one and replays the request; for static keys the
 * refresh is a no-op and the 401 is returned unchanged.
 */
class AuthMiddleware
{
    public function __construct(
        protected AuthStrategy $strategy,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $authenticated = $this->strategy->apply($request);

            return $handler($authenticated, $options)->then(
                function (ResponseInterface $response) use ($handler, $request, $options) {
                    if ($response->getStatusCode() !== 401 || ($options['laraclient_reauthed'] ?? false)) {
                        return $response;
                    }

                    if (! $this->strategy->refresh()) {
                        return $response;
                    }

                    $options['laraclient_reauthed'] = true;

                    return $handler($this->strategy->apply($request), $options);
                }
            );
        };
    }
}
