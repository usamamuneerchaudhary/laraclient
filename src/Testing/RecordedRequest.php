<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Testing;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * One captured request
 *
 *     LaraClient::assertSent(fn (RecordedRequest $r) =>
 *         $r->isPost() && $r->urlIs('*charges') && $r->json('amount') === 500
 *     );
 */
class RecordedRequest
{
    public function __construct(
        public readonly string $connection,
        public readonly RequestInterface $request,
        public readonly ?ResponseInterface $response = null,
    ) {}

    public function method(): string
    {
        return $this->request->getMethod();
    }

    public function url(): string
    {
        return (string) $this->request->getUri();
    }

    public function path(): string
    {
        return $this->request->getUri()->getPath();
    }

    public function urlIs(string $pattern): bool
    {
        return Str::is($pattern, $this->url());
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function header(string $name): string
    {
        return $this->request->getHeaderLine($name);
    }

    public function hasHeader(string $name, ?string $value = null): bool
    {
        if (! $this->request->hasHeader($name)) {
            return false;
        }

        return $value === null || $this->header($name) === $value;
    }

    public function body(): string
    {
        $stream = $this->request->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return (string) $stream;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        $decoded = json_decode($this->body(), true);

        if (! is_array($decoded)) {
            return $key === null ? $decoded : $default;
        }

        return $key === null ? $decoded : Arr::get($decoded, $key, $default);
    }

    /** @return array<string, string> */
    public function query(): array
    {
        parse_str($this->request->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    public function status(): ?int
    {
        return $this->response?->getStatusCode();
    }
}
