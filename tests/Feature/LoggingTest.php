<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Usamamuneerchaudhary\LaraClient\Facades\LaraClient;
use Usamamuneerchaudhary\LaraClient\Models\LaraClientLog;
use Usamamuneerchaudhary\LaraClient\Tests\TestCase;

class LoggingTest extends TestCase
{
    #[Test]
    public function it_never_writes_credentials_to_the_log(): void
    {
        LaraClient::fake(['*' => LaraClient::response(['ok' => true])]);

        LaraClient::connection('test')->post('login', ['password' => 'hunter2']);

        $log = LaraClientLog::query()->latest('id')->firstOrFail();

        $this->assertSame('[redacted]', $log->request_headers['Authorization'] ?? null);
        $this->assertStringNotContainsString('secret-token', json_encode($log->toArray()) ?: '');
        $this->assertStringNotContainsString('hunter2', (string) $log->request_body);
    }

    #[Test]
    public function it_logs_failures_too(): void
    {
        LaraClient::fake(['*' => LaraClient::response(['error' => 'nope'], 500)]);

        LaraClient::connection('test')->withoutRetrying()->get('things');

        // v1 only logged on the success path, so the calls worth debugging
        // were the ones that left no trace.
        $this->assertSame(1, LaraClientLog::query()->failed()->count());
    }

    #[Test]
    public function it_records_the_attempt_number(): void
    {
        LaraClient::fake(['*' => LaraClient::response([], 503)]);

        LaraClient::connection('test')->retry(2, 1)->get('things');

        $this->assertSame([1, 2], LaraClientLog::query()->orderBy('id')->pluck('attempt')->all());
    }
}
