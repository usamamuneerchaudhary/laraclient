<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Middleware;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Usamamuneerchaudhary\LaraClient\Exceptions\CircuitOpenException;
use Usamamuneerchaudhary\LaraClient\Support\CircuitBreaker;

/**
 * Fails fast while an upstream is down.
 *
 * Sits outside the retry middleware, so it judges the final outcome of a
 * request rather than counting each individual attempt as a failure.
 */
class CircuitBreakerMiddleware
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected CircuitBreaker $breaker,
        protected array $config,
        protected string $connection,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            if ($this->breaker->isOpen()) {
                return Create::rejectionFor(
                    new CircuitOpenException($this->breaker->retryAfter(), $this->connection)
                );
            }

            return $handler($request, $options)->then(
                function (ResponseInterface $response) {
                    $this->countsAsFailure($response)
                        ? $this->breaker->recordFailure()
                        : $this->breaker->recordSuccess();

                    return $response;
                },
                function (mixed $reason) {
                    // A 422 from our own bad payload should not trip the circuit.
                    if ($reason instanceof ConnectException) {
                        $this->breaker->recordFailure();
                    }

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    protected function countsAsFailure(ResponseInterface $response): bool
    {
        return in_array(
            $response->getStatusCode(),
            $this->config['sample_statuses'] ?? [500, 502, 503, 504],
            true,
        );
    }
}
