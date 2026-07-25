<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Traits\Macroable;
use Usamamuneerchaudhary\LaraClient\Support\ConnectionConfig;
use Usamamuneerchaudhary\LaraClient\Testing\Fake;
use Usamamuneerchaudhary\LaraClient\Testing\Recorder;

/**
 * Resolves connections and forwards calls to the default one.
 *
 * @method static Response|PromiseInterface get(string $uri, array<string, mixed> $query = [])
 * @method static Response|PromiseInterface post(string $uri, array<string, mixed> $data = [])
 * @method static Response|PromiseInterface put(string $uri, array<string, mixed> $data = [])
 * @method static Response|PromiseInterface patch(string $uri, array<string, mixed> $data = [])
 * @method static Response|PromiseInterface delete(string $uri, array<string, mixed> $data = [])
 */
class LaraClientManager
{
    use Macroable {
        Macroable::__call as macroCall;
    }

    /** @var array<string, PendingRequest> */
    protected array $resolved = [];

    /** @var array{beforeSending: list<Closure>, afterResponse: list<Closure>} */
    protected array $hooks = [
        'beforeSending' => [],
        'afterResponse' => [],
    ];

    protected ?Fake $fake = null;

    protected ?Recorder $recorder = null;

    public function __construct(
        protected ConfigRepository $config,
        protected Dispatcher $events,
        protected CacheFactory $cache,
    ) {}

    /**
     * A fresh, fully configured request for the given connection.
     */
    public function connection(?string $name = null): PendingRequest
    {
        $name ??= $this->defaultConnection();

        $config = ConnectionConfig::resolve($name, $this->config->get('lara_client', []));

        $request = new PendingRequest(
            $config,
            $this->events,
            $this->cache,
            $this->hooks,
        );

        if ($this->fake !== null) {
            return $request->withHandler(fn () => $this->fake->handler($name, $config->baseUri()));
        }

        if ($this->recorder()?->intercepts()) {
            return $request->withHandler(fn () => $this->recorder()->handler($name));
        }

        return $request;
    }

    /** Alias that reads better at some call sites. */
    public function to(string $name): PendingRequest
    {
        return $this->connection($name);
    }

    public function defaultConnection(): string
    {
        return (string) $this->config->get('lara_client.default', 'example');
    }

    /** @return list<string> */
    public function connectionNames(): array
    {
        return array_keys($this->config->get('lara_client.connections', []));
    }

    // --- Global hooks -----------------------------------------------------

    /**
     * Runs before every outbound request on every connection. Good place for
     * correlation IDs, tenant headers or request signing.
     *
     *     LaraClient::beforeSending(function ($request, $method, $url, &$options) {
     *         $options['headers']['X-Request-Id'] = (string) Str::uuid();
     *     });
     */
    public function beforeSending(Closure $callback): static
    {
        $this->hooks['beforeSending'][] = $callback;

        return $this;
    }

    /**
     * Runs after every response
     */
    public function afterResponse(Closure $callback): static
    {
        $this->hooks['afterResponse'][] = $callback;

        return $this;
    }

    public function flushHooks(): static
    {
        $this->hooks = ['beforeSending' => [], 'afterResponse' => []];

        return $this;
    }

    // --- Testing ----------------------------------------------------------

    /**
     * Takes the network out of the loop.
     *
     *     LaraClient::fake([
     *         'github/*'  => LaraClient::response(['login' => 'usama']),
     *         'stripe/*'  => LaraClient::response([], 402),
     *         '*'         => LaraClient::response([], 404),
     *     ]);
     */
    /**
     * @param  array<string, mixed>|Closure|null  $responses
     */
    public function fake(array|Closure|null $responses = null): Fake
    {
        $this->fake ??= new Fake;

        if ($responses !== null) {
            $this->fake->register($responses);
        }

        return $this->fake;
    }

    /**
     * @param  array<string, mixed>|string  $body
     * @param  array<string, string|list<string>>  $headers
     */
    public function response(array|string $body = [], int $status = 200, array $headers = []): Testing\FakeResponse
    {
        return new Testing\FakeResponse($body, $status, $headers);
    }

    /**
     * Simulates a transport failure so retry and circuit-breaker paths can be
     * tested without unplugging anything.
     */
    public function failedConnection(string $message = 'Connection refused'): Testing\FakeConnectionFailure
    {
        return new Testing\FakeConnectionFailure($message);
    }

    public function isFaking(): bool
    {
        return $this->fake !== null;
    }

    public function stopFaking(): static
    {
        $this->fake = null;

        return $this;
    }

    public function recorder(): ?Recorder
    {
        $config = $this->config->get('lara_client.recorder', []);

        if (($config['mode'] ?? 'off') === 'off') {
            return null;
        }

        return $this->recorder ??= new Recorder($config);
    }

    // --- Assertions -------------------------------------------------------

    public function assertSent(Closure $callback): void
    {
        $this->fake()->assertSent($callback);
    }

    public function assertNotSent(Closure $callback): void
    {
        $this->fake()->assertNotSent($callback);
    }

    public function assertSentCount(int $count): void
    {
        $this->fake()->assertSentCount($count);
    }

    public function assertNothingSent(): void
    {
        $this->fake()->assertNothingSent();
    }

    /** @return list<Testing\RecordedRequest> */
    public function recorded(?Closure $filter = null): array
    {
        return $this->fake()->recorded($filter);
    }

    /**
     * Unknown methods are forwarded to the default connection, so
     * LaraClient::get('users') works without naming it.
     */
    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $arguments);
        }

        return $this->connection()->{$method}(...$arguments);
    }
}
