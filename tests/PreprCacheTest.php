<?php

namespace Preprio\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Preprio\Prepr;

class PreprCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'prepr.cache' => true,
            'prepr.cache_time' => 3600,
        ]);
    }

    public function test_live_and_cached_public_api_responses_are_identical(): void
    {
        $payload = [
            'items' => [
                ['id' => '1', 'title' => 'Hello', 'meta' => ['locale' => 'nl-NL']],
            ],
            'total' => 1,
        ];

        Http::fake([
            'cdn.prepr.io/*' => Http::response($payload, 200),
        ]);

        $live = (new Prepr)
            ->path('publications')
            ->query(['fields' => 'items', 'limit' => 10])
            ->get();

        $cached = (new Prepr)
            ->path('publications')
            ->query(['fields' => 'items', 'limit' => 10])
            ->get();

        $this->assertPublicApiParity($live, $cached);
        $this->assertSame($payload, $live->getResponse());
        Http::assertSentCount(1);
    }

    public function test_live_and_cached_error_responses_are_identical(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['message' => 'Not found'], 404),
        ]);

        $live = (new Prepr)->path('publications/missing')->get();
        $cached = (new Prepr)->path('publications/missing')->get();

        $this->assertPublicApiParity($live, $cached);
        $this->assertSame(404, $cached->getStatusCode());
        Http::assertSentCount(1);
    }

    public function test_different_query_strings_use_separate_cache_entries(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::sequence()
                ->push(['page' => 1], 200)
                ->push(['page' => 2], 200),
        ]);

        $pageOne = (new Prepr)->path('publications')->query(['page' => 1])->get();
        $pageTwo = (new Prepr)->path('publications')->query(['page' => 2])->get();

        $this->assertSame(['page' => 1], $pageOne->getResponse());
        $this->assertSame(['page' => 2], $pageTwo->getResponse());
        Http::assertSentCount(2);

        (new Prepr)->path('publications')->query(['page' => 1])->get();
        (new Prepr)->path('publications')->query(['page' => 2])->get();

        Http::assertSentCount(2);
    }

    public function test_cache_stores_versioned_status_and_body_payload(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 201),
        ]);

        (new Prepr)
            ->path('publications')
            ->get();

        $payload = Cache::get($this->cacheKey('https://cdn.prepr.io/publications'));

        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['version']);
        $this->assertSame(201, $payload['status']);
        $this->assertSame('{"ok":true}', $payload['body']);
        $this->assertArrayNotHasKey('request', $payload);
    }

    public function test_legacy_cache_payload_is_ignored_and_request_is_refetched(): void
    {
        $key = $this->cacheKey('https://cdn.prepr.io/publications');

        Cache::put($key, [
            'request' => null,
            'response' => ['stale' => true],
        ], 3600);

        Http::fake([
            'cdn.prepr.io/*' => Http::response(['fresh' => true], 200),
        ]);

        $result = (new Prepr)
            ->path('publications')
            ->get();

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(['fresh' => true], $result->getResponse());
        Http::assertSentCount(1);
    }

    public function test_invalid_cache_payload_is_ignored(): void
    {
        $key = $this->cacheKey('https://cdn.prepr.io/publications');

        Cache::put($key, [
            'version' => 1,
            'status' => '200',
            'body' => ['not' => 'a string'],
        ], 3600);

        Http::fake([
            'cdn.prepr.io/*' => Http::response(['fresh' => true], 200),
        ]);

        $result = (new Prepr)
            ->path('publications')
            ->get();

        $this->assertSame(['fresh' => true], $result->getResponse());
        Http::assertSentCount(1);
    }

    public function test_cached_hit_does_not_perform_http_request(): void
    {
        $body = json_encode(['items' => []], JSON_THROW_ON_ERROR);
        $key = $this->cacheKey('https://cdn.prepr.io/publications');

        Cache::put($key, [
            'version' => 1,
            'status' => 200,
            'body' => $body,
        ], 3600);

        Http::fake();

        $result = (new Prepr)
            ->path('publications')
            ->get();

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(['items' => []], $result->getResponse());
        $this->assertSame($body, $result->getRawResponse());
        Http::assertNothingSent();
    }

    public function test_post_requests_are_not_cached(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['created' => true], 201),
        ]);

        (new Prepr)
            ->path('publications')
            ->params(['title' => 'Hello'])
            ->post();

        (new Prepr)
            ->path('publications')
            ->params(['title' => 'Hello'])
            ->post();

        Http::assertSentCount(2);
        $this->assertNull(Cache::get($this->cacheKey('https://cdn.prepr.io/publications')));
    }

    public function test_cache_is_skipped_when_disabled(): void
    {
        config(['prepr.cache' => false]);

        Http::fake([
            'cdn.prepr.io/*' => Http::response(['items' => []], 200),
        ]);

        (new Prepr)->path('publications')->get();
        (new Prepr)->path('publications')->get();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/publications');
    }

    public function test_different_authorization_tokens_use_separate_cache_entries(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::sequence()
                ->push(['token' => 'a'], 200)
                ->push(['token' => 'b'], 200),
        ]);

        $first = (new Prepr)->authorization('token-a')->path('publications')->get();
        $second = (new Prepr)->authorization('token-b')->path('publications')->get();

        $this->assertSame(['token' => 'a'], $first->getResponse());
        $this->assertSame(['token' => 'b'], $second->getResponse());
        Http::assertSentCount(2);

        (new Prepr)->authorization('token-a')->path('publications')->get();
        (new Prepr)->authorization('token-b')->path('publications')->get();

        Http::assertSentCount(2);
    }

    protected function assertPublicApiParity(Prepr $live, Prepr $cached): void
    {
        $this->assertSame($live->getStatusCode(), $cached->getStatusCode());
        $this->assertSame($live->getResponse(), $cached->getResponse());
        $this->assertSame($live->getRawResponse(), $cached->getRawResponse());
    }

    protected function cacheKey(string $url): string
    {
        return 'prepr.rest.v1.'.md5($url.'test-token');
    }
}
