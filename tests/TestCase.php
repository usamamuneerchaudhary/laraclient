<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Usamamuneerchaudhary\LaraClient\Facades\LaraClient;
use Usamamuneerchaudhary\LaraClient\LaraClientServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [LaraClientServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['LaraClient' => LaraClient::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');

        $app['config']->set('lara_client.default', 'test');
        $app['config']->set('lara_client.connections.test', [
            'base_uri' => 'https://api.test.local/v1/',
            'auth' => ['driver' => 'bearer', 'token' => 'secret-token'],
        ]);
    }
}
