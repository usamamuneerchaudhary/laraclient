<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Usamamuneerchaudhary\LaraClient\Facades\LaraClient;
use Usamamuneerchaudhary\LaraClient\Tests\TestCase;

class PaginationTest extends TestCase
{
    #[Test]
    public function it_walks_numbered_pages_until_a_short_page_arrives(): void
    {
        LaraClient::fake([
            '*' => [
                LaraClient::response(['data' => [['id' => 1], ['id' => 2]]]),
                LaraClient::response(['data' => [['id' => 3]]]),
            ],
        ]);

        $ids = LaraClient::connection('test')
            ->paginate('users')
            ->perPage(2)
            ->lazy()
            ->pluck('id')
            ->all();

        $this->assertSame([1, 2, 3], $ids);
        LaraClient::assertSentCount(2);
    }

    #[Test]
    public function it_follows_cursors(): void
    {
        LaraClient::fake([
            '*' => [
                LaraClient::response(['data' => [['id' => 1]], 'meta' => ['next_cursor' => 'abc']]),
                LaraClient::response(['data' => [['id' => 2]], 'meta' => ['next_cursor' => null]]),
            ],
        ]);

        $ids = LaraClient::connection('test')
            ->paginate('users')
            ->strategy('cursor')
            ->lazy()
            ->pluck('id')
            ->all();

        $this->assertSame([1, 2], $ids);
    }

    #[Test]
    public function max_pages_stops_an_endpoint_that_never_ends(): void
    {
        LaraClient::fake(['*' => LaraClient::response(['data' => [['id' => 1], ['id' => 2]]])]);

        $items = LaraClient::connection('test')
            ->paginate('users')
            ->perPage(2)
            ->maxPages(3)
            ->collect();

        $this->assertCount(6, $items);
        LaraClient::assertSentCount(3);
    }
}
