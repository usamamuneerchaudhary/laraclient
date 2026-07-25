<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Usamamuneerchaudhary\LaraClient\Support\Redactor;

class RedactorTest extends TestCase
{
    protected function redactor(): Redactor
    {
        return new Redactor([
            'headers' => ['authorization', 'x-api-key'],
            'body' => ['password', 'client_secret', 'api_key'],
            'replacement' => '[redacted]',
        ]);
    }

    #[Test]
    public function it_masks_credential_headers_regardless_of_casing(): void
    {
        $masked = $this->redactor()->headers([
            'Authorization' => 'Bearer sk_live_abc123',
            'X-API-Key' => 'nope',
            'Accept' => 'application/json',
        ]);

        $this->assertSame('[redacted]', $masked['Authorization']);
        $this->assertSame('[redacted]', $masked['X-API-Key']);
        $this->assertSame('application/json', $masked['Accept']);
    }

    #[Test]
    public function it_masks_nested_body_keys(): void
    {
        $payload = json_encode([
            'user' => ['email' => 'a@b.com', 'password' => 'hunter2'],
            'items' => [
                ['client_secret' => 'shh', 'qty' => 1],
            ],
        ]);

        $result = json_decode((string) $this->redactor()->payload($payload, 'application/json'), true);

        $this->assertSame('[redacted]', $result['user']['password']);
        $this->assertSame('a@b.com', $result['user']['email']);
        $this->assertSame('[redacted]', $result['items'][0]['client_secret']);
        $this->assertSame(1, $result['items'][0]['qty']);
    }

    #[Test]
    public function it_masks_credentials_carried_in_the_query_string(): void
    {
        $url = $this->redactor()->url('https://api.test.local/v1/weather?q=london&api_key=live_123');

        $this->assertStringContainsString('q=london', $url);
        $this->assertStringNotContainsString('live_123', $url);
    }

    #[Test]
    public function it_leaves_unparseable_payloads_alone(): void
    {
        $html = '<html><body>Gateway timeout</body></html>';

        $this->assertSame($html, $this->redactor()->payload($html, 'text/html'));
    }

    #[Test]
    public function it_truncates_oversized_bodies(): void
    {
        $truncated = $this->redactor()->truncate(str_repeat('a', 500), 100);

        $this->assertStringContainsString('truncated', (string) $truncated);
        $this->assertLessThan(500, strlen((string) $truncated));
    }
}
