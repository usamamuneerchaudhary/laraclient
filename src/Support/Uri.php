<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Support;

class Uri
{
    /**
     * Joins a base URI and a path without producing a double slash
     * https://api.example.com/v2/.
     */
    public static function join(string $base, string $path): string
    {
        if (static::isAbsolute($path)) {
            return $path;
        }

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    public static function isAbsolute(string $uri): bool
    {
        return (bool) preg_match('#^https?://#i', $uri);
    }

    /**
     * Fills {placeholders} in a path from an array, URL-encoding each value,
     * and returns the leftovers so they can be sent as query parameters.
     *
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function expand(string $path, array $params): array
    {
        $used = [];

        // Placeholder names are not always word characters: OpenAPI specs in
        // the wild use {a-b}, {user.id} and {tenant_id} freely.
        $expanded = preg_replace_callback(
            '/\{([^{}\/]+)\}/',
            function (array $matches) use ($params, &$used): string {
                $key = $matches[1];

                if (! array_key_exists($key, $params)) {
                    return $matches[0];
                }

                $used[] = $key;

                return rawurlencode((string) $params[$key]);
            },
            $path,
        ) ?? $path;

        return [$expanded, array_diff_key($params, array_flip($used))];
    }
}
