<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Testing;

use Closure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

/**
 * Stubs the transport and records what went out.
 *
 * Patterns are globs matched against either the full URL or the shorthand
 * "connection/path", so both of these work and mean the same thing:
 *
 *     'https://api.github.com/user*' => ...
 *     'github/user*'                 => ...
 *
 * The first matching pattern wins, so put specific patterns before catch-alls.
 * An unmatched request returns 200 with an empty body rather than reaching the
 * network — a fake that silently makes real calls is worse than no fake.
 */
class Fake
{
    /** @var array<string, list<FakeResponse|FakeConnectionFailure|Closure>> */
    protected array $stubs = [];

    /** @var list<RecordedRequest> */
    protected array $recorded = [];

    /**
     * @param  array<string, mixed>|array<string, list<FakeResponse|FakeConnectionFailure|Closure>>  $responses
     */
    public function register(array|Closure $responses): static
    {
        if ($responses instanceof Closure) {
            $this->stubs['*'][] = $responses;

            return $this;
        }

        foreach ($responses as $pattern => $response) {
            foreach ($this->sequence($response) as $item) {
                $this->stubs[(string) $pattern][] = $item;
            }
        }

        return $this;
    }

    /**
     * A list of responses for one pattern is a sequence: the first call gets
     * the first response, and so on. Useful for "fails, then succeeds".
     */
    /** @return list<mixed> */
    protected function sequence(mixed $response): array
    {
        return is_array($response) && array_is_list($response) && $response !== []
            ? $response
            : [$response];
    }

    /**
     * Builds the Guzzle handler that stands in for the network.
     *
     * @param  string  $baseUri  Connection base URI so shorthand patterns are
     *                           relative to it (e.g. `github/user*` matches
     *                           against `/v1/user` when base is `…/v1/`).
     */
    public function handler(string $connection, string $baseUri = ''): Closure
    {
        return function (RequestInterface $request, array $options) use ($connection, $baseUri): PromiseInterface {
            $stub = $this->match($connection, $request, $baseUri);

            if ($stub instanceof Closure) {
                $stub = $stub(new RecordedRequest($connection, $request)) ?? new FakeResponse;
            }

            if ($stub instanceof FakeConnectionFailure) {
                $this->recorded[] = new RecordedRequest($connection, $request);

                return Create::rejectionFor(
                    new ConnectException($stub->message, new PsrRequest($request->getMethod(), $request->getUri()))
                );
            }

            $psr = $stub instanceof FakeResponse
                ? $stub->toPsr()
                : (new FakeResponse)->toPsr();

            $this->recorded[] = new RecordedRequest($connection, $request, $psr);

            return Create::promiseFor($psr);
        };
    }

    protected function match(string $connection, RequestInterface $request, string $baseUri = ''): FakeResponse|FakeConnectionFailure|Closure|null
    {
        $url = (string) $request->getUri();
        $shorthand = $connection.'/'.$this->relativePath($request, $baseUri);

        foreach ($this->stubs as $pattern => $responses) {
            if ($responses === []) {
                continue;
            }

            if ($pattern !== '*'
                && ! Str::is($pattern, $url)
                && ! Str::is($pattern, $shorthand)
            ) {
                continue;
            }

            // Sequences consume one entry per call; the last one repeats so a
            // test does not fall off the end when it retries.
            return count($responses) > 1
                ? array_shift($this->stubs[$pattern])
                : $responses[0];
        }

        return null;
    }

    /**
     * Path relative to the connection base URI, so patterns like `test/users*`
     * match a request to `https://api.test.local/v1/users` when the base is
     * `https://api.test.local/v1/`.
     */
    protected function relativePath(RequestInterface $request, string $baseUri): string
    {
        $path = ltrim($request->getUri()->getPath(), '/');

        if ($baseUri === '') {
            return $path;
        }

        $basePath = ltrim((string) (parse_url($baseUri, PHP_URL_PATH) ?? ''), '/');

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            return ltrim(substr($path, strlen($basePath)), '/');
        }

        return $path;
    }

    // --- Assertions -------------------------------------------------------

    public function assertSent(Closure $callback): void
    {
        Assert::assertTrue(
            $this->recorded($callback) !== [],
            'An expected LaraClient request was not sent. '.$this->summarise(),
        );
    }

    public function assertNotSent(Closure $callback): void
    {
        Assert::assertCount(
            0,
            $this->recorded($callback),
            'An unexpected LaraClient request was sent. '.$this->summarise(),
        );
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount(
            $count,
            $this->recorded,
            "Expected {$count} LaraClient request(s). ".$this->summarise(),
        );
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame(
            [],
            $this->recorded,
            'Expected no LaraClient requests. '.$this->summarise(),
        );
    }

    /**
     * @param  list<Closure(RecordedRequest): bool>  $callbacks
     */
    public function assertSentInOrder(array $callbacks): void
    {
        Assert::assertCount(
            count($callbacks),
            $this->recorded,
            'The number of requests does not match the expected order. '.$this->summarise(),
        );

        foreach ($callbacks as $index => $callback) {
            Assert::assertTrue(
                (bool) $callback($this->recorded[$index]),
                "The request at position {$index} did not match. ".$this->summarise(),
            );
        }
    }

    /** @return list<RecordedRequest> */
    /** @return list<RecordedRequest> */
    public function recorded(?Closure $filter = null): array
    {
        if ($filter === null) {
            return $this->recorded;
        }

        return array_values(array_filter(
            $this->recorded,
            static fn (RecordedRequest $request): bool => (bool) $filter($request),
        ));
    }

    public function flush(): static
    {
        $this->recorded = [];

        return $this;
    }

    /**
     * Failure messages list what actually went out, which is usually enough to
     * spot the mismatch without reaching for a debugger.
     */
    protected function summarise(): string
    {
        if ($this->recorded === []) {
            return 'No requests were sent.';
        }

        $lines = array_map(
            static fn (RecordedRequest $r): string => '  '.$r->method().' '.$r->url(),
            $this->recorded,
        );

        return "Requests sent:\n".implode("\n", $lines);
    }
}
