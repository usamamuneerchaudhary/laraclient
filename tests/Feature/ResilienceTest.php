<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Usamamuneerchaudhary\LaraClient\Exceptions\CircuitOpenException;
use Usamamuneerchaudhary\LaraClient\Exceptions\ConnectionFailedException;
use Usamamuneerchaudhary\LaraClient\Exceptions\RateLimitExceededException;
use Usamamuneerchaudhary\LaraClient\Facades\LaraClient;
use Usamamuneerchaudhary\LaraClient\Tests\TestCase;

class ResilienceTest extends TestCase
{
    #[Test]
    public function it_retries_retryable_statuses_and_stops_at_the_limit(): void
    {
        LaraClient::fake(['*' => LaraClient::response(['error' => 'boom'], 503)]);

        $response = LaraClient::connection('test')
            ->retry(3, 1)
            ->get('things');

        $this->assertSame(503, $response->status());
        LaraClient::assertSentCount(3);
    }

    #[Test]
    public function it_stops_retrying_once_a_request_succeeds(): void
    {
        LaraClient::fake([
            '*' => [
                LaraClient::response([], 503),
                LaraClient::response(['ok' => true], 200),
            ],
        ]);

        $response = LaraClient::connection('test')->retry(4, 1)->get('things');

        $this->assertTrue($response->ok());
        LaraClient::assertSentCount(2);
    }

    #[Test]
    public function it_does_not_replay_unsafe_verbs_by_default(): void
    {
        LaraClient::fake(['*' => LaraClient::response([], 500)]);

        LaraClient::connection('test')->retry(3, 1)->post('charges', ['amount' => 100]);

        // A retried POST could charge the customer twice.
        LaraClient::assertSentCount(1);
    }

    #[Test]
    public function connection_failures_become_a_typed_exception(): void
    {
        LaraClient::fake(['*' => LaraClient::failedConnection('Name or service not known')]);

        $this->expectException(ConnectionFailedException::class);

        LaraClient::connection('test')->withoutRetrying()->get('things');
    }

    #[Test]
    public function the_circuit_opens_after_repeated_server_errors(): void
    {
        config(['lara_client.connections.test.circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 2,
            'cooldown' => 60,
            'sample_statuses' => [500, 503],
        ]]);

        LaraClient::fake(['*' => LaraClient::response([], 503)]);

        LaraClient::connection('test')->withoutRetrying()->get('a');
        LaraClient::connection('test')->withoutRetrying()->get('b');

        $this->expectException(CircuitOpenException::class);

        LaraClient::connection('test')->withoutRetrying()->get('c');
    }

    #[Test]
    public function the_throttle_throws_rather_than_blocking_the_worker(): void
    {
        LaraClient::fake(['*' => LaraClient::response()]);

        $client = LaraClient::connection('test')->throttle(limit: 2, window: 60);

        $client->get('a');
        $client->get('b');

        $this->expectException(RateLimitExceededException::class);

        $client->get('c');
    }

    #[Test]
    public function throttling_is_scoped_per_connection(): void
    {
        config(['lara_client.connections.other' => [
            'base_uri' => 'https://other.test.local/',
        ]]);

        LaraClient::fake(['*' => LaraClient::response()]);

        LaraClient::connection('test')->throttle(1, 60)->get('a');

        // A throttled connection must not stall an unrelated one, which is
        // exactly what v1's single global cache key did.
        $response = LaraClient::connection('other')->throttle(1, 60)->get('a');

        $this->assertTrue($response->successful());
    }
}
