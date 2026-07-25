<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Middleware;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Usamamuneerchaudhary\LaraClient\Support\Backoff;

/**
 * Retries with exponential backoff and jitter, bounded by a max attempt count.
 *
 * Sits outside the rate limiter and logger so every attempt is throttled and
 * logged, and inside idempotency so all attempts share one Idempotency-Key.
 */
class RetryMiddleware
{
    protected Backoff $backoff;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config,
        protected string $connection,
    ) {
        $this->backoff = Backoff::fromConfig($config);
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            return $this->attempt($handler, $request, $options, 1);
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function attempt(
        callable $handler,
        RequestInterface $request,
        array $options,
        int $attempt,
    ): PromiseInterface {
        $options['laraclient_attempt'] = $attempt;

        return $handler($request, $options)->then(
            function (ResponseInterface $response) use ($handler, $request, $options, $attempt) {
                if (! $this->shouldRetryResponse($request, $response, $attempt)) {
                    return $response;
                }

                $this->sleep($this->delayFor($attempt, $response));

                return $this->attempt($handler, $request, $options, $attempt + 1);
            },
            function (mixed $reason) use ($handler, $request, $options, $attempt) {
                if (! $this->shouldRetryFailure($reason, $attempt)) {
                    return Create::rejectionFor($reason);
                }

                $this->sleep($this->delayFor($attempt, null));

                return $this->attempt($handler, $request, $options, $attempt + 1);
            },
        );
    }

    protected function shouldRetryResponse(
        RequestInterface $request,
        ResponseInterface $response,
        int $attempt,
    ): bool {
        if (! $this->enabled() || $attempt >= $this->maxAttempts()) {
            return false;
        }

        if (! $this->methodIsRetryable($request->getMethod())) {
            return false;
        }

        return in_array(
            $response->getStatusCode(),
            $this->config['statuses'] ?? [429, 500, 502, 503, 504],
            true,
        );
    }

    protected function shouldRetryFailure(mixed $reason, int $attempt): bool
    {
        if (! $this->enabled() || $attempt >= $this->maxAttempts()) {
            return false;
        }

        if (! ($this->config['retry_on_connection_error'] ?? true)) {
            return false;
        }

        return $reason instanceof ConnectException;
    }

    /**
     * A provider that tells us when to come back is more reliable than our own
     * backoff curve, so Retry-After wins when present.
     */
    protected function delayFor(int $attempt, ?ResponseInterface $response): int
    {
        if ($response !== null && ($this->config['respect_retry_after'] ?? true)) {
            $retryAfter = Backoff::parseRetryAfter($response->getHeaderLine('Retry-After') ?: null);

            if ($retryAfter !== null) {
                return min($retryAfter * 1000, (int) ($this->config['max_delay'] ?? 10_000));
            }
        }

        return $this->backoff->delayFor($attempt);
    }

    protected function methodIsRetryable(string $method): bool
    {
        $methods = $this->config['methods'] ?? ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'];

        return in_array(strtoupper($method), array_map('strtoupper', $methods), true);
    }

    protected function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    protected function maxAttempts(): int
    {
        return max(1, (int) ($this->config['times'] ?? 3));
    }

    protected function sleep(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
