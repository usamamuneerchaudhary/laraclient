<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient;

use ArrayAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use Psr\Http\Message\ResponseInterface;
use Stringable;
use Usamamuneerchaudhary\LaraClient\Exceptions\RequestException;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Response implements ArrayAccess, Stringable
{
    use Macroable;

    /**
     * Decoded once and reused.
     */
    protected mixed $decoded = null;

    protected bool $hasDecoded = false;

    public function __construct(
        protected ResponseInterface $psr,
        protected string $method = 'GET',
        protected string $url = '',
        protected ?string $connection = null,
        protected float $durationMs = 0.0,
    ) {}

    // --- Body -----------------------------------------------------------

    public function body(): string
    {
        $stream = $this->psr->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return (string) $stream;
    }

    /**
     * Decoded JSON
     *
     *     $response->json();                 // whole payload as an array
     *     $response->json('data.0.email');   // one value
     *     $response->json('meta.total', 0);  // with a fallback
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if (! $this->hasDecoded) {
            $this->decoded = json_decode($this->body(), true);
            $this->hasDecoded = true;
        }

        if (! is_array($this->decoded)) {
            return $key === null ? $this->decoded : $default;
        }

        return $key === null ? $this->decoded : Arr::get($this->decoded, $key, $default);
    }

    /** @return array<array-key, mixed> */
    public function array(?string $key = null): array
    {
        $value = $this->json($key);

        return is_array($value) ? $value : [];
    }

    public function object(?string $key = null): mixed
    {
        $value = $key === null
            ? json_decode($this->body())
            : $this->json($key);

        return is_array($value) ? json_decode(json_encode($value) ?: 'null') : $value;
    }

    /** @return Collection<array-key, mixed> */
    public function collect(?string $key = null): Collection
    {
        return Collection::make($this->array($key));
    }

    /**
     * True when the body parsed as JSON. Useful for spotting an HTML error page
     * served with a 200
     */
    public function isJson(): bool
    {
        return $this->json() !== null || trim($this->body()) === 'null';
    }

    // --- Status ---------------------------------------------------------

    public function status(): int
    {
        return $this->psr->getStatusCode();
    }

    public function reason(): string
    {
        return $this->psr->getReasonPhrase();
    }

    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function ok(): bool
    {
        return $this->status() === 200;
    }

    public function created(): bool
    {
        return $this->status() === 201;
    }

    public function noContent(): bool
    {
        return $this->status() === 204;
    }

    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    public function failed(): bool
    {
        return $this->clientError() || $this->serverError();
    }

    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    public function unauthorized(): bool
    {
        return $this->status() === 401;
    }

    public function forbidden(): bool
    {
        return $this->status() === 403;
    }

    public function notFound(): bool
    {
        return $this->status() === 404;
    }

    public function tooManyRequests(): bool
    {
        return $this->status() === 429;
    }

    // --- Headers --------------------------------------------------------

    public function header(string $name): string
    {
        return $this->psr->getHeaderLine($name);
    }

    /** @return array<string, list<string>> */
    public function headers(): array
    {
        return $this->psr->getHeaders();
    }

    public function fromCache(): bool
    {
        return in_array($this->header('X-LaraClient-Cache'), ['hit', 'revalidated'], true);
    }

    // --- Context --------------------------------------------------------

    public function method(): string
    {
        return $this->method;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function connection(): ?string
    {
        return $this->connection;
    }

    public function durationMs(): float
    {
        return $this->durationMs;
    }

    public function psr(): ResponseInterface
    {
        return $this->psr;
    }

    /**
     * A short, log-safe description of the body for exception messages.
     */
    public function summary(int $length = 200): string
    {
        $body = trim($this->body());

        if ($body === '') {
            return $this->reason();
        }

        return strlen($body) > $length
            ? substr($body, 0, $length).'…'
            : $body;
    }

    // --- Control flow ---------------------------------------------------

    /**
     * Throws on 4xx/5xx. The optional callback runs first, so you can branch
     * before deciding:
     *
     *     $response->throw(fn ($r) => $r->notFound() ?: report($r->json()));
     */
    public function throw(?callable $callback = null): static
    {
        if (! $this->failed()) {
            return $this;
        }

        if ($callback !== null) {
            $callback($this);
        }

        throw new RequestException($this, $this->connection);
    }

    public function throwIf(bool|callable $condition): static
    {
        $shouldThrow = is_callable($condition) ? $condition($this) : $condition;

        return $shouldThrow ? $this->throw() : $this;
    }

    public function onError(callable $callback): static
    {
        if ($this->failed()) {
            $callback($this);
        }

        return $this;
    }

    public function onSuccess(callable $callback): static
    {
        if ($this->successful()) {
            $callback($this);
        }

        return $this;
    }

    // --- Array access ---------------------------------------------------

    public function offsetExists(mixed $offset): bool
    {
        return Arr::has($this->array(), (string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->json((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('LaraClient responses are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('LaraClient responses are read-only.');
    }

    public function __toString(): string
    {
        return $this->body();
    }

    // --- Back-compat ----------------------------------------------------

    /**
     * @deprecated Use json(), array() or object()
     */
    public function getData(): mixed
    {
        return $this->object();
    }

    /** @deprecated Use status(). */
    public function getStatusCode(): int
    {
        return $this->status();
    }
}
