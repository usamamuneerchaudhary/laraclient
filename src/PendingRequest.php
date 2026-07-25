<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Usamamuneerchaudhary\LaraClient\Auth\AuthFactory;
use Usamamuneerchaudhary\LaraClient\Contracts\AuthStrategy;
use Usamamuneerchaudhary\LaraClient\Exceptions\ConnectionFailedException;
use Usamamuneerchaudhary\LaraClient\Middleware\AuthMiddleware;
use Usamamuneerchaudhary\LaraClient\Middleware\CacheMiddleware;
use Usamamuneerchaudhary\LaraClient\Middleware\CircuitBreakerMiddleware;
use Usamamuneerchaudhary\LaraClient\Middleware\IdempotencyMiddleware;
use Usamamuneerchaudhary\LaraClient\Middleware\RateLimitMiddleware;
use Usamamuneerchaudhary\LaraClient\Middleware\RetryMiddleware;
use Usamamuneerchaudhary\LaraClient\Middleware\TelemetryMiddleware;
use Usamamuneerchaudhary\LaraClient\Pagination\Paginator;
use Usamamuneerchaudhary\LaraClient\Support\CircuitBreaker;
use Usamamuneerchaudhary\LaraClient\Support\ConnectionConfig;
use Usamamuneerchaudhary\LaraClient\Support\RateLimiter;
use Usamamuneerchaudhary\LaraClient\Support\Redactor;
use Usamamuneerchaudhary\LaraClient\Support\Uri;

/**
 * A configured, not-yet-sent request against one connection.
 *
 * Every fluent method returns a clone, so a base request can be shared and
 * specialised without one caller's timeout leaking into another's:
 *
 *     $github = LaraClient::connection('github');
 *     $github->timeout(2)->get('rate_limit');
 *     $github->get('user');            // still the configured timeout
 */
class PendingRequest
{
    use Conditionable;
    use Macroable;

    /** @var array<string, string|list<string>> */
    protected array $headers = [];

    /** @var array<string, mixed> */
    protected array $query = [];

    /** @var array<string, mixed> */
    protected array $options = [];

    protected string $bodyFormat = 'json';

    protected ?AuthStrategy $authOverride = null;

    protected bool $async = false;

    protected bool $throwOnError = false;

    protected ?Closure $handlerFactory = null;

    /**
     * @param  array{beforeSending: list<Closure>, afterResponse: list<Closure>}  $globalHooks
     */
    public function __construct(
        protected ConnectionConfig $config,
        protected Dispatcher $events,
        protected CacheFactory $cache,
        protected array $globalHooks = [
            'beforeSending' => [],
            'afterResponse' => [],
        ],
        ?Closure $handlerFactory = null,
    ) {
        $this->handlerFactory = $handlerFactory;
    }

    // --- Fluent configuration -------------------------------------------

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function withHeader(string $name, string $value): static
    {
        return $this->withHeaders([$name => $value]);
    }

    public function accept(string $contentType): static
    {
        return $this->withHeader('Accept', $contentType);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function withQuery(array $query): static
    {
        $clone = clone $this;
        $clone->query = array_merge($clone->query, $query);

        return $clone;
    }

    public function withToken(string $token, string $prefix = 'Bearer'): static
    {
        return $this->withAuth(new Auth\BearerAuth($token, $prefix));
    }

    public function withBasicAuth(string $username, string $password): static
    {
        return $this->withAuth(new Auth\BasicAuth($username, $password));
    }

    public function withAuth(AuthStrategy $strategy): static
    {
        $clone = clone $this;
        $clone->authOverride = $strategy;

        return $clone;
    }

    public function withoutAuth(): static
    {
        return $this->withAuth(new Auth\NullAuth);
    }

    public function timeout(int|float $seconds): static
    {
        return $this->override(['timeout' => $seconds]);
    }

    public function connectTimeout(int|float $seconds): static
    {
        return $this->override(['connect_timeout' => $seconds]);
    }

    /**
     * @param  list<int>|null  $statuses
     */
    public function retry(int $times, int $baseDelayMs = 200, ?array $statuses = null): static
    {
        return $this->override(['retry' => array_filter([
            'enabled' => true,
            'times' => $times,
            'base_delay' => $baseDelayMs,
            'statuses' => $statuses,
        ], static fn ($value) => $value !== null)]);
    }

    public function withoutRetrying(): static
    {
        return $this->override(['retry' => ['enabled' => false]]);
    }

    /**
     * Retry unsafe verbs too. Off by default because replaying a POST can
     * create duplicates
     */
    public function retryUnsafeMethods(): static
    {
        return $this->override(['retry' => [
            'methods' => ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'POST', 'PATCH'],
        ]]);
    }

    /**
     * @param  list<string>  $varyOn
     */
    public function cacheFor(int $seconds, array $varyOn = []): static
    {
        $clone = $this->override(['cache' => ['enabled' => true, 'ttl' => $seconds]]);

        if ($varyOn !== []) {
            $clone->options['laraclient_cache_vary'] = $varyOn;
        }

        return $clone;
    }

    /**
     * Skips the cache for this call but still stores the fresh response.
     */
    public function fresh(): static
    {
        $clone = clone $this;
        $clone->options['laraclient_cache_bypass'] = true;

        return $clone;
    }

    public function withoutCache(): static
    {
        return $this->override(['cache' => ['enabled' => false]]);
    }

    public function throttle(int $limit, int $window = 60, string $onLimit = 'throw'): static
    {
        return $this->override(['rate_limit' => [
            'enabled' => true,
            'limit' => $limit,
            'window' => $window,
            'on_limit' => $onLimit,
        ]]);
    }

    public function withoutThrottling(): static
    {
        return $this->override(['rate_limit' => ['enabled' => false]]);
    }

    public function withoutCircuitBreaker(): static
    {
        return $this->override(['circuit_breaker' => ['enabled' => false]]);
    }

    public function withoutLogging(): static
    {
        return $this->override(['logging' => ['enabled' => false]]);
    }

    /**
     * Pass a key to make the request replay-safe across process boundaries
     */
    public function idempotent(?string $key = null): static
    {
        $clone = $this->override(['idempotency' => ['enabled' => true]]);
        $clone->options['laraclient_idempotency_key'] = $key ?? (string) Str::uuid();

        return $clone;
    }

    public function asJson(): static
    {
        $clone = clone $this;
        $clone->bodyFormat = 'json';

        return $clone;
    }

    public function asForm(): static
    {
        $clone = clone $this;
        $clone->bodyFormat = 'form_params';

        return $clone->withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }

    public function asMultipart(): static
    {
        $clone = clone $this;
        $clone->bodyFormat = 'multipart';
        unset($clone->headers['Content-Type']);

        return $clone;
    }

    /**
     * Streams the response body to a path instead of buffering it in memory.
     */
    public function sink(string $path): static
    {
        $clone = clone $this;
        $clone->options['sink'] = $path;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->options = array_merge($clone->options, $options);

        return $clone;
    }

    /**
     * Turns 4xx/5xx into a RequestException without calling ->throw() at each
     * call site.
     */
    public function throwOnError(bool $throw = true): static
    {
        $clone = clone $this;
        $clone->throwOnError = $throw;

        return $clone;
    }

    /**
     * Swaps the underlying transport. Used by pooling to share one curl multi
     * handler across concurrent requests, and by the fake and the recorder to
     * take the network out of the loop entirely.
     */
    public function withHandler(Closure $factory): static
    {
        $clone = clone $this;
        $clone->handlerFactory = $factory;

        return $clone;
    }

    public function hasHandler(): bool
    {
        return $this->handlerFactory !== null;
    }

    public function async(): static
    {
        $clone = clone $this;
        $clone->async = true;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function override(array $overrides): static
    {
        $clone = clone $this;
        $clone->config = $clone->config->with($overrides);

        return $clone;
    }

    public function config(): ConnectionConfig
    {
        return $this->config;
    }

    public function name(): string
    {
        return $this->config->name;
    }

    // --- Verbs -----------------------------------------------------------

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): Response|PromiseInterface
    {
        return $this->send('GET', $uri, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function head(string $uri, array $query = []): Response|PromiseInterface
    {
        return $this->send('HEAD', $uri, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): Response|PromiseInterface
    {
        return $this->send('POST', $uri, ['body' => $data]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): Response|PromiseInterface
    {
        return $this->send('PUT', $uri, ['body' => $data]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function patch(string $uri, array $data = []): Response|PromiseInterface
    {
        return $this->send('PATCH', $uri, ['body' => $data]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function delete(string $uri, array $data = []): Response|PromiseInterface
    {
        return $this->send('DELETE', $uri, ['body' => $data]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function options(string $uri, array $query = []): Response|PromiseInterface
    {
        return $this->send('OPTIONS', $uri, ['query' => $query]);
    }

    /**
     * @param  array{query?: array<string, mixed>, body?: array<string, mixed>}  $payload
     */
    public function send(string $method, string $uri, array $payload = []): Response|PromiseInterface
    {
        [$uri, $leftoverParams] = Uri::expand($uri, $payload['query'] ?? []);

        $url = Uri::join($this->config->baseUri(), $uri);

        $options = $this->options;
        $options['headers'] = array_merge(
            $this->config->section('headers'),
            $this->headers,
        );

        $query = array_merge($this->query, $leftoverParams);

        if ($query !== []) {
            $options['query'] = $query;
        }

        if (isset($payload['body']) && $payload['body'] !== []) {
            $options[$this->bodyFormat] = $payload['body'];
        }

        $options['timeout'] = $this->config->get('timeout', 30);
        $options['connect_timeout'] = $this->config->get('connect_timeout', 5);

        // Status codes are decided by our own middleware, not by Guzzle
        // throwing, so every layer sees a real response object.
        $options['http_errors'] = false;

        foreach ($this->globalHooks['beforeSending'] ?? [] as $hook) {
            $hook($this, $method, $url, $options);
        }

        $startedAt = microtime(true);

        $promise = $this->client()
            ->requestAsync($method, $url, $options)
            ->then(
                fn (ResponseInterface $psr) => $this->toResponse($psr, $method, $url, $startedAt),
                fn (mixed $reason) => throw $this->normalise($reason, $url),
            );

        return $this->async ? $promise : $promise->wait();
    }

    protected function toResponse(
        ResponseInterface $psr,
        string $method,
        string $url,
        float $startedAt,
    ): Response {
        $response = new Response(
            $psr,
            $method,
            $url,
            $this->config->name,
            (microtime(true) - $startedAt) * 1000,
        );

        foreach ($this->globalHooks['afterResponse'] ?? [] as $hook) {
            $hook($response);
        }

        return $this->throwOnError ? $response->throw() : $response;
    }

    /**
     *  Everything transport-level is normalised here.
     */
    protected function normalise(mixed $reason, string $url): Throwable
    {
        if ($reason instanceof ConnectException) {
            return new ConnectionFailedException(
                sprintf(
                    'Could not reach [%s] on connection [%s]: %s',
                    $url,
                    $this->config->name,
                    $reason->getMessage(),
                ),
                $url,
                $this->config->name,
                $reason,
            );
        }

        return $reason instanceof Throwable
            ? $reason
            : new ConnectionFailedException((string) $reason, $url, $this->config->name);
    }

    // --- Concurrency ------------------------------------------------------

    /**
     * Fires requests in parallel
     *
     *     $results = LaraClient::connection('github')->pool(fn (Pool $pool) => [
     *         $pool->as('user')->get('user'),
     *         $pool->as('repos')->get('user/repos'),
     *     ]);
     *
     * Failures are returned in place rather than aborting the batch, so one
     * dead endpoint does not cost you the responses that did come back.
     *
     * @return array<string, Response|Throwable>
     */
    public function pool(Closure $callback): array
    {
        $pool = new Pool($this);

        $callback($pool);

        return $pool->wait();
    }

    // --- Pagination -------------------------------------------------------

    /**
     * Walks every page lazily. Only one page is held in memory at a time, so
     * this is safe over very large collections.
     */
    /**
     * @param  array<string, mixed>  $query
     */
    public function paginate(string $uri, array $query = []): Paginator
    {
        return new Paginator($this, $uri, $query, $this->config->section('pagination'));
    }

    // --- Client construction ---------------------------------------------

    public function client(): Client
    {
        return new Client([
            'handler' => $this->handlerStack(),
            'http_errors' => false,
        ]);
    }

    /**
     * Middleware order matters, and each position is deliberate:
     *
     *   cache            a hit short-circuits everything below it
     *   circuit breaker  judges the final outcome, not each attempt
     *   idempotency      one key shared by every retry of a request
     *   retry            re-runs everything below on failure
     *   rate limit       throttles per real network call
     *   auth             inside retry so each attempt can refresh a token;
     *                    outside telemetry so logs see (and redact) credentials
     *   telemetry        records each attempt separately
     */
    protected function handlerStack(): HandlerStack
    {
        $stack = HandlerStack::create(
            $this->handlerFactory !== null ? ($this->handlerFactory)() : null
        );

        $store = $this->cache->store($this->config->get('cache.store'));

        if ($this->config->enabled('cache')) {
            $stack->push(new CacheMiddleware(
                $store,
                $this->config->section('cache'),
                $this->config->name,
            ), 'laraclient.cache');
        }

        if ($this->config->enabled('circuit_breaker')) {
            $stack->push(new CircuitBreakerMiddleware(
                new CircuitBreaker(
                    $store,
                    $this->config->name,
                    (int) $this->config->get('circuit_breaker.failure_threshold', 5),
                    (int) $this->config->get('circuit_breaker.cooldown', 60),
                ),
                $this->config->section('circuit_breaker'),
                $this->config->name,
            ), 'laraclient.circuit_breaker');
        }

        if ($this->config->enabled('idempotency')) {
            $stack->push(
                new IdempotencyMiddleware($this->config->section('idempotency')),
                'laraclient.idempotency',
            );
        }

        if ($this->config->enabled('retry')) {
            $stack->push(new RetryMiddleware(
                $this->config->section('retry'),
                $this->config->name,
            ), 'laraclient.retry');
        }

        if ($this->config->enabled('rate_limit')) {
            $stack->push(new RateLimitMiddleware(
                new RateLimiter(
                    $store,
                    $this->config->name,
                    (int) $this->config->get('rate_limit.limit', 60),
                    (int) $this->config->get('rate_limit.window', 60),
                ),
                $this->config->section('rate_limit'),
                $this->config->name,
            ), 'laraclient.rate_limit');
        }

        $stack->push(new AuthMiddleware($this->authStrategy()), 'laraclient.auth');

        $stack->push(new TelemetryMiddleware(
            $this->events,
            new Redactor($this->config->section('redact')),
            $this->config->section('logging'),
            $this->config->name,
        ), 'laraclient.telemetry');

        return $stack;
    }

    protected function authStrategy(): AuthStrategy
    {
        return $this->authOverride ?? AuthFactory::make(
            $this->config->section('auth'),
            $this->config->name,
            $this->cache->store(),
        );
    }
}
