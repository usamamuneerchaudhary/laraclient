<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Usamamuneerchaudhary\LaraClient\Support\Uri;

class UriTest extends TestCase
{
    #[Test]
    public function it_joins_without_doubling_slashes(): void
    {
        $this->assertSame(
            'https://api.test.local/v1/users',
            Uri::join('https://api.test.local/v1/', '/users'),
        );

        $this->assertSame(
            'https://api.test.local/v1/users',
            Uri::join('https://api.test.local/v1', 'users'),
        );
    }

    #[Test]
    public function it_passes_absolute_urls_through(): void
    {
        $this->assertSame(
            'https://other.example.com/thing',
            Uri::join('https://api.test.local/v1/', 'https://other.example.com/thing'),
        );
    }

    #[Test]
    public function it_expands_path_placeholders_and_returns_the_leftovers(): void
    {
        [$path, $leftover] = Uri::expand('users/{id}/repos', ['id' => 42, 'sort' => 'created']);

        $this->assertSame('users/42/repos', $path);
        $this->assertSame(['sort' => 'created'], $leftover);
    }

    #[Test]
    public function it_encodes_placeholder_values(): void
    {
        [$path] = Uri::expand('search/{term}', ['term' => 'a/b c']);

        $this->assertSame('search/a%2Fb%20c', $path);
    }
}
