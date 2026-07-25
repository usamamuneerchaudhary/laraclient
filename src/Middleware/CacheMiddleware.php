<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Middleware;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Response caching
 *
 * Entries are kept for twice their freshness window. Past the freshness point
 * the body is still on hand, so a stale entry with an ETag can be revalidated
 * with a conditional request: a 304 costs one round trip and no payload, and
 * refreshes the entry instead of re-downloading it.
 *
 * Responses served from cache carry an X-LaraClient-Cache header so the log
 * and the dashboard can tell a cache hit from a real call.
 */
class CacheMiddleware
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected CacheRepository $cache,
        protected array $config,
        protected string $connection,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            if (! $this->applies($request, $options)) {
                return $handler($request, $options);
            }

            $key = $this->keyFor($request, $options);
            $entry = $this->repository()->get($key);

            if (is_array($entry) && $this->isFresh($entry)) {
                return Create::promiseFor($this->toResponse($entry, 'hit'));
            }

            $revalidating = is_array($entry) && $this->canRevalidate($entry);

            if ($revalidating) {
                $request = $request->withHeader('If-None-Match', $entry['etag']);
            }

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($key, $entry, $revalidating) {
                    if ($revalidating && $response->getStatusCode() === 304) {
                        $this->store($key, $this->refreshed($entry));

                        return $this->toResponse($this->refreshed($entry), 'revalidated');
                    }

                    if ($this->isCacheable($response)) {
                        $this->store($key, $this->entryFrom($response));
                    }

                    return $response->withHeader('X-LaraClient-Cache', 'miss');
                }
            );
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function applies(RequestInterface $request, array $options): bool
    {
        if (($options['laraclient_cache_bypass'] ?? false) === true) {
            return false;
        }

        if (! ($this->config['enabled'] ?? false)) {
            return false;
        }

        $methods = array_map('strtoupper', $this->config['methods'] ?? ['GET', 'HEAD']);

        return in_array(strtoupper($request->getMethod()), $methods, true);
    }

    protected function isCacheable(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return false;
        }

        // Honor an upstream that explicitly asks us not to store the response.
        $control = strtolower($response->getHeaderLine('Cache-Control'));

        return ! str_contains($control, 'no-store') && ! str_contains($control, 'private');
    }

    protected function ttl(): int
    {
        return max(1, (int) ($this->config['ttl'] ?? 300));
    }

    /** @return array<string, mixed> */
    protected function entryFrom(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $response->getBody()->rewind();

        return [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $body,
            'etag' => $response->getHeaderLine('ETag') ?: null,
            'fresh_until' => time() + $this->maxAge($response),
            'stored_at' => time(),
        ];
    }

    /**
     * A Cache-Control max-age from the provider is more accurate than our
     * configured TTL, so it wins when it is shorter.
     */
    protected function maxAge(ResponseInterface $response): int
    {
        $control = $response->getHeaderLine('Cache-Control');

        if (preg_match('/max-age=(\d+)/i', $control, $matches) === 1) {
            return min($this->ttl(), max(0, (int) $matches[1]));
        }

        return $this->ttl();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    protected function refreshed(array $entry): array
    {
        $entry['fresh_until'] = time() + $this->ttl();

        return $entry;
    }

    /** @param  array<string, mixed>  $entry */
    protected function isFresh(array $entry): bool
    {
        return ($entry['fresh_until'] ?? 0) > time();
    }

    /** @param  array<string, mixed>  $entry */
    protected function canRevalidate(array $entry): bool
    {
        return ($this->config['respect_etag'] ?? true) && ! empty($entry['etag']);
    }

    /** @param  array<string, mixed>  $entry */
    protected function toResponse(array $entry, string $state): ResponseInterface
    {
        return new PsrResponse(
            $entry['status'],
            ($entry['headers'] ?? []) + [
                'X-LaraClient-Cache' => [$state],
                'X-LaraClient-Cache-Age' => [(string) (time() - ($entry['stored_at'] ?? time()))],
            ],
            $entry['body'] ?? '',
        );
    }

    /** @param  array<string, mixed>  $entry */
    protected function store(string $key, array $entry): void
    {
        $this->repository()->put($key, $entry, $this->ttl() * 2);
    }

    /**
     * Tagged stores let you flush one integration's cache without touching the
     * rest of the application: Cache::tags('laraclient:github')->flush().
     */
    protected function repository(): CacheRepository
    {
        $tag = $this->config['tag'] ?? "laraclient:{$this->connection}";

        // File and database stores cannot tag; fall back to untagged keys.
        if (! method_exists($this->cache, 'tags')) {
            return $this->cache;
        }

        try {
            return $this->cache->tags([$tag]);
        } catch (\BadMethodCallException) {
            return $this->cache;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function keyFor(RequestInterface $request, array $options): string
    {
        $vary = $options['laraclient_cache_vary'] ?? [];

        $varied = '';
        foreach ((array) $vary as $header) {
            $varied .= $header.':'.$request->getHeaderLine($header).';';
        }

        return 'laraclient:cache:'.$this->connection.':'.sha1(
            $request->getMethod().'|'.(string) $request->getUri().'|'.$varied
        );
    }
}
