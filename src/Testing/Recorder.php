<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Testing;

use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Utils as GuzzleUtils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Usamamuneerchaudhary\LaraClient\Exceptions\FixtureNotFoundException;
use Usamamuneerchaudhary\LaraClient\Support\Redactor;

/**
 * Record real responses once, replay them forever.
 *
 *     LARACLIENT_RECORDER=record php artisan test --filter=GithubTest
 *     LARACLIENT_RECORDER=replay php artisan test
 *
 * Fixtures pass through the same redactor as the logs, so recording against a
 * live account does not commit your API keys.
 */
class Recorder
{
    protected Redactor $redactor;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
        ?Redactor $redactor = null,
    ) {
        $this->redactor = $redactor ?? new Redactor([
            'headers' => ['authorization', 'cookie', 'set-cookie', 'x-api-key'],
            'body' => ['access_token', 'refresh_token', 'client_secret', 'password'],
        ]);
    }

    public function mode(): string
    {
        return $this->config['mode'] ?? 'off';
    }

    /**
     * Replay mode replaces the transport; record mode wraps it.
     */
    public function intercepts(): bool
    {
        return in_array($this->mode(), ['record', 'replay'], true);
    }

    public function handler(string $connection): Closure
    {
        return $this->mode() === 'replay'
            ? $this->replayHandler($connection)
            : $this->recordHandler($connection);
    }

    protected function replayHandler(string $connection): Closure
    {
        return function (RequestInterface $request, array $options) use ($connection): PromiseInterface {
            $path = $this->fixturePath($connection, $request);

            if (! is_file($path)) {
                return Create::rejectionFor(
                    FixtureNotFoundException::for($request->getMethod(), (string) $request->getUri(), $path)
                );
            }

            $fixture = json_decode((string) file_get_contents($path), true);

            return Create::promiseFor(new PsrResponse(
                $fixture['status'] ?? 200,
                ($fixture['headers'] ?? []) + ['X-LaraClient-Replayed' => ['1']],
                is_array($fixture['body'] ?? null)
                    ? (json_encode($fixture['body']) ?: '')
                    : (string) ($fixture['body'] ?? ''),
            ));
        };
    }

    protected function recordHandler(string $connection): Closure
    {
        $inner = GuzzleUtils::chooseHandler();

        return function (RequestInterface $request, array $options) use ($inner, $connection): PromiseInterface {
            return $inner($request, $options)->then(
                function (ResponseInterface $response) use ($connection, $request) {
                    $this->write($connection, $request, $response);

                    return $response;
                }
            );
        };
    }

    protected function write(string $connection, RequestInterface $request, ResponseInterface $response): void
    {
        $path = $this->fixturePath($connection, $request);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        $body = (string) $response->getBody();
        $response->getBody()->rewind();

        $decoded = json_decode(
            (string) $this->redactor->payload($body, $response->getHeaderLine('Content-Type')),
            true,
        );

        file_put_contents($path, json_encode([
            'recorded_at' => date(DATE_ATOM),
            'connection' => $connection,
            'method' => $request->getMethod(),
            'uri' => $this->redactor->url((string) $request->getUri()),
            'status' => $response->getStatusCode(),
            'headers' => $this->redactor->headers(
                array_map(static fn (array $v): string => implode(', ', $v), $response->getHeaders())
            ),
            'body' => $decoded ?? $body,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * The fixture name is a hash of whatever match_on says identifies a
     * request, prefixed with a readable slug so the directory stays browsable.
     */
    protected function fixturePath(string $connection, RequestInterface $request): string
    {
        $matchOn = $this->config['match_on'] ?? ['method', 'uri', 'body'];
        $parts = [];

        if (in_array('method', $matchOn, true)) {
            $parts[] = $request->getMethod();
        }

        if (in_array('uri', $matchOn, true)) {
            $parts[] = (string) $request->getUri();
        }

        if (in_array('body', $matchOn, true)) {
            $stream = $request->getBody();

            if ($stream->isSeekable()) {
                $stream->rewind();
                $parts[] = (string) $stream;
                $stream->rewind();
            }
        }

        $slug = strtolower($request->getMethod()).'-'.trim(
            preg_replace('/[^a-z0-9]+/i', '-', $request->getUri()->getPath()) ?? '',
            '-'
        );

        $slug = trim($slug, '-') ?: 'root';

        return rtrim($this->basePath(), '/')."/{$connection}/{$slug}-".substr(sha1(implode('|', $parts)), 0, 10).'.json';
    }

    protected function basePath(): string
    {
        $path = $this->config['path'] ?? 'tests/fixtures/laraclient';

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return function_exists('base_path') ? base_path($path) : $path;
    }
}
