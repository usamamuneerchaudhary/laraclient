<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Testing;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\ResponseInterface;

class FakeResponse
{
    /**
     * @param  array<string, mixed>|string  $body
     * @param  array<string, string|list<string>>  $headers
     */
    public function __construct(
        protected array|string $body = [],
        protected int $status = 200,
        protected array $headers = [],
    ) {}

    public function toPsr(): ResponseInterface
    {
        $body = is_array($this->body)
            ? (json_encode($this->body) ?: '')
            : $this->body;

        $headers = $this->headers;

        if (is_array($this->body) && ! isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return new PsrResponse($this->status, $headers, $body);
    }
}
