<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Auth;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Usamamuneerchaudhary\LaraClient\Contracts\AuthStrategy;
use Usamamuneerchaudhary\LaraClient\Exceptions\LaraClientException;

class AuthFactory
{
    /** @var array<string, Closure(array<string, mixed>, string, CacheRepository): AuthStrategy> */
    protected static array $customDrivers = [];

    /**
     * Register your own scheme (request signing, HMAC, mTLS headers, JWT
     * assertions) without forking the package:
     *
     *     AuthFactory::extend('hmac', fn ($config) => new HmacAuth($config['secret']));
     */
    public static function extend(string $driver, Closure $resolver): void
    {
        static::$customDrivers[$driver] = $resolver;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config, string $connection, CacheRepository $cache): AuthStrategy
    {
        $driver = $config['driver'] ?? 'none';

        if (isset(static::$customDrivers[$driver])) {
            return (static::$customDrivers[$driver])($config, $connection, $cache);
        }

        return match ($driver) {
            'none', '' => new NullAuth,

            'bearer' => new BearerAuth(
                $config['token'] ?? null,
                $config['prefix'] ?? 'Bearer',
            ),

            'basic' => new BasicAuth(
                $config['username'] ?? null,
                $config['password'] ?? null,
            ),

            'header' => new HeaderAuth(
                $config['name'] ?? 'X-API-Key',
                $config['value'] ?? null,
            ),

            'query' => new QueryAuth(
                $config['name'] ?? 'api_key',
                $config['value'] ?? null,
            ),

            'oauth2', 'oauth2_client_credentials' => new OAuth2ClientCredentials(
                cache: $cache,
                connection: $connection,
                tokenUrl: (string) ($config['token_url'] ?? ''),
                clientId: $config['client_id'] ?? null,
                clientSecret: $config['client_secret'] ?? null,
                scopes: $config['scopes'] ?? [],
                leeway: (int) ($config['leeway'] ?? 60),
                authStyle: $config['auth_style'] ?? 'body',
            ),

            default => throw new LaraClientException(
                "Unknown LaraClient auth driver [{$driver}] on connection [{$connection}]. ".
                'Register it with AuthFactory::extend() if it is your own.',
                $connection,
            ),
        };
    }
}
