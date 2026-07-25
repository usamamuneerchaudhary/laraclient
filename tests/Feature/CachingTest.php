<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Usamamuneerchaudhary\LaraClient\Facades\LaraClient;
use Usamamuneerchaudhary\LaraClient\Tests\TestCase;

class CachingTest extends TestCase
{
    #[Test]
    public function a_repeated_get_is_served_from_cache(): void
    {
        LaraClient::fake(['*' => LaraClient::response(['value' => 1])]);

        $client = LaraClient::connection('test')->cacheFor(60);

        $first = $client->get('things');
        $second = $client->get('things');

        $this->assertFalse($first->fromCache());
        $this->assertTrue($second->fromCache());
        $this->assertSame(1, $second->json('value'));

        LaraClient::assertSentCount(1);
    }

    #[Test]
    public function different_query_parameters_are_cached_separately(): void
    {
        LaraClient::fake(['*' => LaraClient::response(['value' => 1])]);

        $client = LaraClient::connection('test')->cacheFor(60);

        $client->get('things', ['page' => 1]);
        $client->get('things', ['page' => 2]);

        LaraClient::assertSentCount(2);
    }

    #[Test]
    public function post_requests_are_never_cached(): void
    {
        LaraClient::fake(['*' => LaraClient::response(['ok' => true])]);

        $client = LaraClient::connection('test')->cacheFor(60);

        $client->post('things', ['a' => 1]);
        $client->post('things', ['a' => 1]);

        LaraClient::assertSentCount(2);
    }

    #[Test]
    public function fresh_bypasses_a_warm_cache(): void
    {
        LaraClient::fake(['*' => LaraClient::response(['value' => 1])]);

        $client = LaraClient::connection('test')->cacheFor(60);

        $client->get('things');
        $client->fresh()->get('things');

        LaraClient::assertSentCount(2);
    }
}
