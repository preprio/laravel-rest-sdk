<?php

namespace Preprio\Tests;

use Illuminate\Support\Facades\Http;
use Preprio\Prepr;

class PreprAutoPagingTest extends TestCase
{
    public function test_auto_paging_aggregates_multiple_pages(): void
    {
        $pageOne = array_map(fn (int $i) => ['id' => (string) $i], range(1, 100));
        $pageTwo = array_map(fn (int $i) => ['id' => (string) $i], range(101, 150));

        Http::fake([
            'cdn.prepr.io/*' => Http::sequence()
                ->push(['items' => $pageOne], 200)
                ->push(['items' => $pageTwo], 200),
        ]);

        $result = (new Prepr)
            ->path('publications')
            ->query([])
            ->autoPaging();

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(150, data_get($result->getResponse(), 'total'));
        $this->assertCount(150, data_get($result->getResponse(), 'items'));
        $this->assertSame('1', data_get($result->getResponse(), 'items.0.id'));
        $this->assertSame('150', data_get($result->getResponse(), 'items.149.id'));
        Http::assertSentCount(2);
    }

    public function test_auto_paging_stops_at_requested_limit(): void
    {
        $pageOne = array_map(fn (int $i) => ['id' => (string) $i], range(1, 100));
        $pageTwo = array_map(fn (int $i) => ['id' => (string) $i], range(101, 200));

        Http::fake([
            'cdn.prepr.io/*' => Http::sequence()
                ->push(['items' => $pageOne], 200)
                ->push(['items' => $pageTwo], 200),
        ]);

        $result = (new Prepr)
            ->path('publications')
            ->query(['limit' => 120])
            ->autoPaging();

        $this->assertSame(120, data_get($result->getResponse(), 'total'));
        $this->assertCount(120, data_get($result->getResponse(), 'items'));
        Http::assertSentCount(2);
    }

    public function test_auto_paging_returns_error_response_when_page_fails(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['message' => 'Nope'], 500),
        ]);

        $result = (new Prepr)
            ->path('publications')
            ->query([])
            ->autoPaging();

        $this->assertSame(500, $result->getStatusCode());
        $this->assertSame(['message' => 'Nope'], $result->getResponse());
    }

    public function test_auto_paging_handles_empty_items(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['items' => []], 200),
        ]);

        $result = (new Prepr)
            ->path('publications')
            ->query([])
            ->autoPaging();

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(['items' => [], 'total' => 0], $result->getResponse());
    }
}
