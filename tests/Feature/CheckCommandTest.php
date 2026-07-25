<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Usamamuneerchaudhary\LaraClient\Facades\LaraClient;
use Usamamuneerchaudhary\LaraClient\Testing\RecordedRequest;
use Usamamuneerchaudhary\LaraClient\Tests\TestCase;

class CheckCommandTest extends TestCase
{
    #[Test]
    public function it_uses_configured_health_path_and_query(): void
    {
        config([
            'lara_client.connections.test.health_path' => 'current.json',
            'lara_client.connections.test.health_query' => ['q' => 'London'],
        ]);

        LaraClient::fake([
            'test/current.json*' => LaraClient::response(['current' => ['temp_c' => 15]], 200),
        ]);

        $this->artisan('laraclient:check', ['connection' => ['test']])
            ->assertSuccessful();

        LaraClient::assertSent(
            fn (RecordedRequest $request): bool => $request->urlIs('*current.json*')
                && ($request->query()['q'] ?? null) === 'London'
        );
    }

    #[Test]
    public function it_fails_when_a_configured_health_path_does_not_succeed(): void
    {
        config(['lara_client.connections.test.health_path' => 'ping']);

        LaraClient::fake(['test/ping*' => LaraClient::response([], 404)]);

        $this->artisan('laraclient:check', ['connection' => ['test']])
            ->assertFailed();
    }

    #[Test]
    public function it_treats_a_root_404_as_reachable_without_a_health_path(): void
    {
        LaraClient::fake(['test/*' => LaraClient::response([], 404)]);

        $this->artisan('laraclient:check', ['connection' => ['test']])
            ->assertSuccessful();
    }

    #[Test]
    public function it_fails_when_credentials_are_rejected(): void
    {
        LaraClient::fake(['*' => LaraClient::response([], 403)]);

        $this->artisan('laraclient:check', ['connection' => ['test']])
            ->assertFailed();
    }
}
