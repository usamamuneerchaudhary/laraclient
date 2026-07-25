<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Facades;

use Illuminate\Support\Facades\Facade;
use Usamamuneerchaudhary\LaraClient\LaraClientManager;
use Usamamuneerchaudhary\LaraClient\Testing\Fake;

/**
 * @method static \Usamamuneerchaudhary\LaraClient\PendingRequest connection(?string $name = null)
 * @method static \Usamamuneerchaudhary\LaraClient\PendingRequest to(string $name)
 * @method static \Usamamuneerchaudhary\LaraClient\Response get(string $uri, array<string, mixed> $query = [])
 * @method static \Usamamuneerchaudhary\LaraClient\Response post(string $uri, array<string, mixed> $data = [])
 * @method static \Usamamuneerchaudhary\LaraClient\Response put(string $uri, array<string, mixed> $data = [])
 * @method static \Usamamuneerchaudhary\LaraClient\Response patch(string $uri, array<string, mixed> $data = [])
 * @method static \Usamamuneerchaudhary\LaraClient\Response delete(string $uri, array<string, mixed> $data = [])
 * @method static \Usamamuneerchaudhary\LaraClient\Response head(string $uri, array<string, mixed> $query = [])
 * @method static \Usamamuneerchaudhary\LaraClient\Testing\FakeResponse response(array<string, mixed>|string $body = [], int $status = 200, array<string, string|list<string>> $headers = [])
 * @method static \Usamamuneerchaudhary\LaraClient\Testing\FakeConnectionFailure failedConnection(string $message = 'Connection refused')
 * @method static \Usamamuneerchaudhary\LaraClient\LaraClientManager beforeSending(\Closure $callback)
 * @method static \Usamamuneerchaudhary\LaraClient\LaraClientManager afterResponse(\Closure $callback)
 * @method static void assertSent(\Closure $callback)
 * @method static void assertNotSent(\Closure $callback)
 * @method static void assertSentCount(int $count)
 * @method static void assertNothingSent()
 * @method static array<int, \Usamamuneerchaudhary\LaraClient\Testing\RecordedRequest> recorded(?\Closure $filter = null)
 * @method static string defaultConnection()
 * @method static list<string> connectionNames()
 *
 * @see LaraClientManager
 */
class LaraClient extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaraClientManager::class;
    }

    /**
     * Facade::fake() is the idiom Laravel developers reach for first, so it is
     * wired to swap the real manager rather than relying on __callStatic.
     *
     * @param  array<string, mixed>|\Closure|null  $responses
     */
    public static function fake(array|\Closure|null $responses = null): Fake
    {
        return static::getFacadeRoot()->fake($responses);
    }
}
