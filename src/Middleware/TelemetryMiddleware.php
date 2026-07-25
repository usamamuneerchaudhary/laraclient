<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Middleware;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Usamamuneerchaudhary\LaraClient\Events\RequestFailed;
use Usamamuneerchaudhary\LaraClient\Events\RequestSending;
use Usamamuneerchaudhary\LaraClient\Events\ResponseReceived;
use Usamamuneerchaudhary\LaraClient\Models\LaraClientLog;
use Usamamuneerchaudhary\LaraClient\Support\Redactor;

/**
 * Fires events and writes the log row.
 *
 * Sits inside the retry middleware so each attempt is recorded separately
 * "this call succeeded on the third try" is exactly the thing you want to see
 * when an integration is flaky.
 */
class TelemetryMiddleware
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Dispatcher $events,
        protected Redactor $redactor,
        protected array $config,
        protected string $connection,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $attempt = (int) ($options['laraclient_attempt'] ?? 1);
            $startedAt = microtime(true);

            $this->events->dispatch(new RequestSending($this->connection, $request, $attempt));

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request, $startedAt, $attempt) {
                    $duration = $this->elapsed($startedAt);
                    $cached = $response->getHeaderLine('X-LaraClient-Cache');

                    $this->events->dispatch(new ResponseReceived(
                        $this->connection,
                        $request,
                        $response,
                        $duration,
                        in_array($cached, ['hit', 'revalidated'], true),
                    ));

                    $this->record($request, $response, null, $duration, $attempt, $cached);

                    return $response;
                },
                function (mixed $reason) use ($request, $startedAt, $attempt) {
                    $duration = $this->elapsed($startedAt);

                    if ($reason instanceof Throwable) {
                        $this->events->dispatch(
                            new RequestFailed($this->connection, $request, $reason, $duration)
                        );

                        $this->record($request, null, $reason, $duration, $attempt, '');
                    }

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    protected function record(
        RequestInterface $request,
        ?ResponseInterface $response,
        ?Throwable $exception,
        float $duration,
        int $attempt,
        string $cacheState,
    ): void {
        if (! ($this->config['enabled'] ?? true)) {
            return;
        }

        $status = $response?->getStatusCode();

        if (($this->config['only_failures'] ?? false) && $status !== null && $status < 400) {
            return;
        }

        $max = (int) ($this->config['max_body_length'] ?? 64_000);

        // Logging must never be the reason a request fails.
        try {
            LaraClientLog::create([
                'connection' => $this->connection,
                'method' => $request->getMethod(),
                'endpoint' => $this->redactor->url((string) $request->getUri()),
                'status' => $status,
                'duration_ms' => (int) round($duration),
                'attempt' => $attempt,
                'cached' => in_array($cacheState, ['hit', 'revalidated'], true),
                'request_headers' => $this->redactor->headers($this->flatten($request->getHeaders())),
                'request_body' => ($this->config['store_request_body'] ?? true)
                    ? $this->redactor->truncate(
                        $this->redactor->payload(
                            $this->readBody($request),
                            $request->getHeaderLine('Content-Type'),
                        ),
                        $max,
                    )
                    : null,
                'response_headers' => $response
                    ? $this->redactor->headers($this->flatten($response->getHeaders()))
                    : null,
                'response_body' => ($this->config['store_response_body'] ?? true) && $response
                    ? $this->redactor->truncate(
                        $this->redactor->payload(
                            $this->readBody($response),
                            $response->getHeaderLine('Content-Type'),
                        ),
                        $max,
                    )
                    : null,
                'exception' => $exception?->getMessage(),
            ]);
        } catch (Throwable) {
            // Swallow: a broken log table should not break the integration.
        }
    }

    /**
     * Reads a body without consuming it for the caller.
     */
    protected function readBody(RequestInterface|ResponseInterface $message): ?string
    {
        $stream = $message->getBody();

        if (! $stream->isReadable()) {
            return null;
        }

        if ($stream->isSeekable()) {
            $stream->rewind();
            $contents = $stream->getContents();
            $stream->rewind();

            return $contents;
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $headers
     * @return array<string, string>
     */
    protected function flatten(array $headers): array
    {
        $flat = [];

        foreach ($headers as $name => $values) {
            $flat[$this->canonicalHeaderName((string) $name)] = implode(', ', $values);
        }

        return $flat;
    }

    protected function canonicalHeaderName(string $name): string
    {
        return implode('-', array_map(
            static fn (string $part): string => ucfirst(strtolower($part)),
            explode('-', $name),
        ));
    }

    protected function elapsed(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000;
    }
}
