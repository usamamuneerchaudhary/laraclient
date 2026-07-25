<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Usamamuneerchaudhary\LaraClient\Exceptions\ConnectionFailedException;
use Usamamuneerchaudhary\LaraClient\Facades\LaraClient;
use Usamamuneerchaudhary\LaraClient\Pool;
use Usamamuneerchaudhary\LaraClient\Response;
use Usamamuneerchaudhary\LaraClient\Tests\TestCase;

class PoolTest extends TestCase
{
    #[Test]
    public function it_returns_results_keyed_by_name(): void
    {
        LaraClient::fake([
            'test/user*' => LaraClient::response(['login' => 'usama']),
            'test/repos*' => LaraClient::response(['count' => 12]),
        ]);

        $results = LaraClient::connection('test')->pool(fn (Pool $pool) => [
            $pool->as('user')->get('user'),
            $pool->as('repos')->get('repos'),
        ]);

        $this->assertSame('usama', $results['user']->json('login'));
        $this->assertSame(12, $results['repos']->json('count'));
    }

    #[Test]
    public function one_failure_does_not_discard_the_rest(): void
    {
        LaraClient::fake([
            'test/good*' => LaraClient::response(['ok' => true]),
            'test/bad*' => LaraClient::failedConnection(),
        ]);

        $results = LaraClient::connection('test')->withoutRetrying()->pool(fn (Pool $pool) => [
            $pool->as('good')->get('good'),
            $pool->as('bad')->get('bad'),
        ]);

        $this->assertInstanceOf(Response::class, $results['good']);
        $this->assertInstanceOf(ConnectionFailedException::class, $results['bad']);
    }
}
