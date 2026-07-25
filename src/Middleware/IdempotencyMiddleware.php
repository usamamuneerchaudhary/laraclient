<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;

/**
 * Stamps an Idempotency-Key on unsafe requests.
 *
 * Deliberately outside the retry middleware: all attempts for one logical
 * request must carry the same key, otherwise a retried payment creates a
 * second charge instead of returning the first one.
 */
class IdempotencyMiddleware
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config,
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $header = $this->config['header'] ?? 'Idempotency-Key';

            if ($this->applies($request) && ! $request->hasHeader($header)) {
                $request = $request->withHeader(
                    $header,
                    $options['laraclient_idempotency_key'] ?? (string) Str::uuid(),
                );
            }

            return $handler($request, $options);
        };
    }

    protected function applies(RequestInterface $request): bool
    {
        $methods = array_map('strtoupper', $this->config['methods'] ?? ['POST', 'PATCH']);

        return in_array(strtoupper($request->getMethod()), $methods, true);
    }
}
