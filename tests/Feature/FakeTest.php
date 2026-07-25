<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Usamamuneerchaudhary\LaraClient\Facades\LaraClient;
use Usamamuneerchaudhary\LaraClient\Testing\RecordedRequest;
use Usamamuneerchaudhary\LaraClient\Tests\TestCase;

class FakeTest extends TestCase
{
    #[Test]
    public function it_returns_stubbed_responses(): void
    {
        LaraClient::fake([
            'test/users*' => LaraClient::response(['data' => [['id' => 1, 'name' => 'Usama']]]),
        ]);

        $response = LaraClient::connection('test')->get('users');

        $this->assertTrue($response->ok());
        $this->assertSame('Usama', $response->json('data.0.name'));
        $this->assertSame(1, $response->collect('data')->count());
    }

    #[Test]
    public function it_records_requests_for_assertions(): void
    {
        LaraClient::fake(['*' => LaraClient::response(['ok' => true])]);

        LaraClient::connection('test')->post('charges', ['amount' => 500]);

        LaraClient::assertSentCount(1);

        LaraClient::assertSent(fn (RecordedRequest $request): bool => $request->isPost()
            && $request->urlIs('*charges')
            && $request->json('amount') === 500);

        LaraClient::assertNotSent(fn (RecordedRequest $request): bool => $request->isGet());
    }

    #[Test]
    public function it_applies_configured_authentication(): void
    {
        LaraClient::fake(['*' => LaraClient::response()]);

        LaraClient::connection('test')->get('me');

        LaraClient::assertSent(
            fn (RecordedRequest $request): bool => $request->header('Authorization') === 'Bearer secret-token'
        );
    }

    #[Test]
    public function a_sequence_returns_each_response_in_turn(): void
    {
        LaraClient::fake([
            'test/flaky*' => [
                LaraClient::response(['error' => 'nope'], 500),
                LaraClient::response(['ok' => true], 200),
            ],
        ]);

        $first = LaraClient::connection('test')->withoutRetrying()->get('flaky');
        $second = LaraClient::connection('test')->withoutRetrying()->get('flaky');

        $this->assertSame(500, $first->status());
        $this->assertSame(200, $second->status());
    }

    #[Test]
    public function unmatched_requests_never_reach_the_network(): void
    {
        LaraClient::fake();

        $response = LaraClient::connection('test')->get('anything');

        $this->assertTrue($response->successful());
        LaraClient::assertSentCount(1);
    }
}
