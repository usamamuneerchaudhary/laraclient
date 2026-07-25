<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Middleware;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Usamamuneerchaudhary\LaraClient\Exceptions\RateLimitExceededException;
use Usamamuneerchaudhary\LaraClient\Support\Backoff;
use Usamamuneerchaudhary\LaraClient\Support\RateLimiter;

/**
 * Client-side throttling, scoped to one connection.
 *
 * The default behavior is to throw rather than sleep. A queued job can catch
 * RateLimitExceededException and release itself back onto the queue, which
 * frees the worker instead of parking a PHP process on a sleep()
 */
class RateLimitMiddleware
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected RateLimiter $limiter,
        protected array $config,
        protected string $connection,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $blocked = $this->waitForSlot();

            if ($blocked !== null) {
                return Create::rejectionFor($blocked);
            }

            return $handler($request, $options)->then(
                function (ResponseInterface $response) {
                    // The provider's own answer beats our local count.
                    if ($response->getStatusCode() === 429) {
                        $retryAfter = Backoff::parseRetryAfter(
                            $response->getHeaderLine('Retry-After') ?: null
                        ) ?? $this->limiter->availableIn();

                        $this->limiter->blockFor($retryAfter);
                    }

                    return $response;
                }
            );
        };
    }

    protected function waitForSlot(): ?RateLimitExceededException
    {
        if ($this->limiter->attempt()) {
            return null;
        }

        $availableIn = $this->limiter->availableIn();

        if (($this->config['on_limit'] ?? 'throw') !== 'wait') {
            return new RateLimitExceededException($availableIn, $this->connection);
        }

        $maxWait = (int) ($this->config['max_wait'] ?? 10);

        if ($availableIn > $maxWait) {
            return new RateLimitExceededException(
                $availableIn,
                $this->connection,
                sprintf(
                    'Rate limit reached for connection [%s]. The window rolls over in %ds, which exceeds max_wait of %ds.',
                    $this->connection,
                    $availableIn,
                    $maxWait,
                ),
            );
        }

        sleep($availableIn);

        return $this->limiter->attempt()
            ? null
            : new RateLimitExceededException($this->limiter->availableIn(), $this->connection);
    }
}
