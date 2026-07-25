<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Utils as GuzzleUtils;
use Throwable;

/**
 * Collects requests and runs them concurrently.
 *
 * All requests in a pool share one curl multi handler. Without that they would
 * each build their own and run one after another, the calls would look
 * parallel in the code and be entirely sequential on the wire.
 *
 * @method self get(string $uri, array<string, mixed> $query = [])
 * @method self post(string $uri, array<string, mixed> $data = [])
 * @method self put(string $uri, array<string, mixed> $data = [])
 * @method self patch(string $uri, array<string, mixed> $data = [])
 * @method self delete(string $uri, array<string, mixed> $data = [])
 * @method self head(string $uri, array<string, mixed> $query = [])
 */
class Pool
{
    /** @var array<array-key, PromiseInterface> */
    protected array $promises = [];

    protected ?string $key = null;

    protected PendingRequest $request;

    public function __construct(PendingRequest $request)
    {
        if ($request->hasHandler()) {
            $this->request = $request;

            return;
        }

        $shared = GuzzleUtils::chooseHandler();

        $this->request = $request->withHandler(static fn () => $shared);
    }

    /**
     * Names the next request so the result can be read back by key rather than
     * by position.
     */
    public function as(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Applies fluent configuration to the next request only.
     */
    public function using(callable $callback): static
    {
        $this->request = $callback($this->request);

        return $this;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): static
    {
        $key = $this->key ?? count($this->promises);
        $this->key = null;

        $this->promises[$key] = $this->request->async()->{$method}(...$arguments);

        return $this;
    }

    /**
     * Settles every promise. A rejected request yields its exception in place
     * instead of taking the whole batch down with it.
     *
     * @return array<array-key, Response|Throwable>
     */
    public function wait(): array
    {
        $settled = Utils::settle($this->promises)->wait();

        return array_map(
            static fn (array $result) => $result['state'] === 'fulfilled'
                ? $result['value']
                : $result['reason'],
            $settled,
        );
    }

    /** @return array<array-key, PromiseInterface> */
    public function promises(): array
    {
        return $this->promises;
    }
}
