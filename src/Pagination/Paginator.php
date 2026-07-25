<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Pagination;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use IteratorAggregate;
use Traversable;
use Usamamuneerchaudhary\LaraClient\PendingRequest;
use Usamamuneerchaudhary\LaraClient\Response;

/**
 * Walks a paginated endpoint one page at a time.
 *
 * Configure the strategy once per connection and the call site stops caring:
 *
 *     LaraClient::connection('github')
 *         ->paginate('user/repos')
 *         ->each(fn (array $repo) => Repo::sync($repo));
 *
 * Backed by a LazyCollection, so memory stays flat whether the endpoint has
 * three pages or three thousand.
 *
 * @implements IteratorAggregate<int, mixed>
 */
class Paginator implements IteratorAggregate
{
    protected ?int $maxPages = null;

    protected ?int $limit = null;

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected PendingRequest $request,
        protected string $uri,
        protected array $query = [],
        protected array $config = [],
    ) {}

    public function strategy(string $strategy): static
    {
        $this->config['strategy'] = $strategy;

        return $this;
    }

    public function perPage(int $perPage): static
    {
        $this->config['per_page'] = $perPage;

        return $this;
    }

    public function dataKey(string $key): static
    {
        $this->config['data_key'] = $key;

        return $this;
    }

    /**
     * A safety valve for endpoints that never stop paginating.
     */
    public function maxPages(int $pages): static
    {
        $this->maxPages = $pages;

        return $this;
    }

    public function take(int $items): static
    {
        $this->limit = $items;

        return $this;
    }

    /**
     * @return LazyCollection<int, mixed>
     */
    public function lazy(): LazyCollection
    {
        $collection = new LazyCollection(function (): \Generator {
            yield from match ($this->config['strategy'] ?? 'page') {
                'cursor' => $this->cursorPages(),
                'offset' => $this->offsetPages(),
                'link_header' => $this->linkHeaderPages(),
                default => $this->numberedPages(),
            };
        });

        return $this->limit !== null ? $collection->take($this->limit) : $collection;
    }

    /** @return Collection<int, mixed> */
    public function collect(): Collection
    {
        return $this->lazy()->collect();
    }

    public function each(callable $callback): void
    {
        $this->lazy()->each($callback);
    }

    /**
     * Yields whole pages rather than individual records
     *
     * @return LazyCollection<int, list<mixed>>
     */
    public function chunks(): LazyCollection
    {
        return LazyCollection::make(function (): \Generator {
            $page = (int) ($this->query[$this->pageParam()] ?? 1);
            $seen = 0;

            while (true) {
                $response = $this->fetch([
                    $this->pageParam() => $page,
                    $this->perPageParam() => $this->pageSize(),
                ]);

                $items = $this->items($response);

                if ($items === []) {
                    return;
                }

                yield $items;

                $seen++;
                $page++;

                if ($this->exhausted($items, $seen)) {
                    return;
                }
            }
        });
    }

    public function getIterator(): Traversable
    {
        return $this->lazy()->getIterator();
    }

    // --- Strategies -------------------------------------------------------

    protected function numberedPages(): \Generator
    {
        $page = (int) ($this->query[$this->pageParam()] ?? 1);
        $fetched = 0;

        while (true) {
            $response = $this->fetch([
                $this->pageParam() => $page,
                $this->perPageParam() => $this->pageSize(),
            ]);

            $items = $this->items($response);

            // Yield without preserving page-local keys: `yield from` would
            // re-emit 0, 1, … each page and iterator_to_array would overwrite.
            foreach ($items as $item) {
                yield $item;
            }

            $fetched++;
            $page++;

            if ($this->exhausted($items, $fetched)) {
                return;
            }
        }
    }

    protected function offsetPages(): \Generator
    {
        $offset = (int) ($this->query['offset'] ?? 0);
        $limit = $this->pageSize();
        $fetched = 0;

        while (true) {
            $response = $this->fetch([
                $this->config['offset_param'] ?? 'offset' => $offset,
                $this->config['limit_param'] ?? 'limit' => $limit,
            ]);

            $items = $this->items($response);

            foreach ($items as $item) {
                yield $item;
            }

            $fetched++;
            $offset += $limit;

            if ($this->exhausted($items, $fetched)) {
                return;
            }
        }
    }

    protected function cursorPages(): \Generator
    {
        $cursor = $this->query[$this->config['cursor_param'] ?? 'cursor'] ?? null;
        $fetched = 0;

        while (true) {
            $params = [$this->perPageParam() => $this->pageSize()];

            if ($cursor !== null) {
                $params[$this->config['cursor_param'] ?? 'cursor'] = $cursor;
            }

            $response = $this->fetch($params);
            $items = $this->items($response);

            foreach ($items as $item) {
                yield $item;
            }

            $fetched++;
            $cursor = $response->json($this->config['next_cursor_key'] ?? 'meta.next_cursor');

            // A cursor API is authoritative about whether more pages exist, so
            // an empty page mid-run does not necessarily mean the end.
            if (blank($cursor) || ($this->maxPages !== null && $fetched >= $this->maxPages)) {
                return;
            }
        }
    }

    /**
     * RFC 8288 Link headers, as used by GitHub
     */
    protected function linkHeaderPages(): \Generator
    {
        $uri = $this->uri;
        $params = [$this->perPageParam() => $this->pageSize()];
        $fetched = 0;

        while (true) {
            $response = $this->request->get($uri, array_merge($this->query, $params));

            foreach ($this->items($response) as $item) {
                yield $item;
            }

            $fetched++;
            $next = $this->nextLink($response->header('Link'));

            if ($next === null || ($this->maxPages !== null && $fetched >= $this->maxPages)) {
                return;
            }

            $uri = $next;
            $params = []; // the next link already carries its own parameters
        }
    }

    protected function nextLink(string $linkHeader): ?string
    {
        if ($linkHeader === '') {
            return null;
        }

        foreach (explode(',', $linkHeader) as $part) {
            if (preg_match('/<([^>]+)>\s*;\s*rel="?next"?/i', trim($part), $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    // --- Helpers ----------------------------------------------------------

    /**
     * @param  array<string, mixed>  $params
     */
    protected function fetch(array $params): Response
    {
        /** @var Response $response */
        $response = $this->request->get($this->uri, array_merge($this->query, $params));

        return $response;
    }

    /** @return list<mixed> */
    protected function items(Response $response): array
    {
        $key = $this->config['data_key'] ?? 'data';

        // Some APIs return a bare top-level array with no envelope.
        $items = $key === ''
            ? $response->json()
            : Arr::get($response->json() ?? [], $key);

        return is_array($items) ? $items : [];
    }

    /**
     * @param  list<mixed>  $items
     */
    protected function exhausted(array $items, int $pagesFetched): bool
    {
        if ($items === [] || count($items) < $this->pageSize()) {
            return true;
        }

        return $this->maxPages !== null && $pagesFetched >= $this->maxPages;
    }

    protected function pageParam(): string
    {
        return $this->config['page_param'] ?? 'page';
    }

    protected function perPageParam(): string
    {
        return $this->config['per_page_param'] ?? 'per_page';
    }

    protected function pageSize(): int
    {
        return max(1, (int) ($this->config['per_page'] ?? 100));
    }
}
