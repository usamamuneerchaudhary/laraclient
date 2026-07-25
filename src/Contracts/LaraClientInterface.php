<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Contracts;

interface LaraClientInterface
{
    /**
     * @param  array<string, mixed>  $queryParams
     */
    public function get(string $uri, array $queryParams = []): mixed;

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): mixed;

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): mixed;

    /**
     * @param  array<string, mixed>  $data
     */
    public function patch(string $uri, array $data = []): mixed;

    /**
     * @param  array<string, mixed>  $data
     */
    public function delete(string $uri, array $data = []): mixed;
}
