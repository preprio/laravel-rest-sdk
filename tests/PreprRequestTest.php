<?php

namespace Preprio\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Preprio\Prepr;

class PreprRequestTest extends TestCase
{
    public function test_get_returns_decoded_response_and_status(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['items' => [['id' => '1']]], 200),
        ]);

        $result = (new Prepr)
            ->path('publications')
            ->get();

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(['items' => [['id' => '1']]], $result->getResponse());
        $this->assertSame('{"items":[{"id":"1"}]}', $result->getRawResponse());
    }

    public function test_get_sends_query_string_and_authorization_header(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->path('publications')
            ->query(['fields' => 'items', 'limit' => 5])
            ->get();

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://cdn.prepr.io/publications?fields=items&limit=5'
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_authorization_and_url_can_be_overridden_per_request(): void
    {
        Http::fake([
            'radio-api.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->url('https://radio-api.prepr.io/v5/')
            ->authorization('radio-token')
            ->path('guides/{id}', ['id' => 'guide-1'])
            ->get();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://radio-api.prepr.io/v5/guides/guide-1'
                && $request->hasHeader('Authorization', 'Bearer radio-token');
        });
    }

    public function test_custom_headers_are_sent(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->path('publications')
            ->headers(['X-Custom' => 'abc'])
            ->get();

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Custom', 'abc'));
    }

    public function test_post_put_patch_and_delete_use_expected_methods(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)->path('publications')->params(['title' => 'A'])->post();
        (new Prepr)->path('publications/1')->params(['title' => 'B'])->put();
        (new Prepr)->path('publications/1')->params(['title' => 'C'])->patch();
        (new Prepr)->path('publications/1')->delete();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === 'https://cdn.prepr.io/publications');
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->url() === 'https://cdn.prepr.io/publications/1');
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && $request->url() === 'https://cdn.prepr.io/publications/1');
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === 'https://cdn.prepr.io/publications/1');
    }

    public function test_as_json_sends_json_body_on_post(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->path('publications')
            ->asJson()
            ->params(['title' => 'JSON'])
            ->post();

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->hasHeader('Content-Type', 'application/json')
                && $request['title'] === 'JSON';
        });
    }

    public function test_non_json_body_decodes_to_null_response(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response('not-json', 200),
        ]);

        $result = (new Prepr)->path('publications')->get();

        $this->assertSame(200, $result->getStatusCode());
        $this->assertNull($result->getResponse());
        $this->assertSame('not-json', $result->getRawResponse());
    }

    public function test_config_headers_are_merged_into_requests(): void
    {
        config(['prepr.headers' => ['X-Env' => 'test']]);

        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)->path('publications')->get();

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Env', 'test'));
    }
}
