<?php

namespace Preprio\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Preprio\Prepr;
use ReflectionProperty;

class PreprFileTest extends TestCase
{
    public function test_small_file_upload_posts_multipart_source(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['id' => 'asset-1'], 200),
        ]);

        $result = (new Prepr)
            ->path('assets')
            ->params(['body' => 'Example'])
            ->file('fake-image-bytes', 'image.jpg');

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(['id' => 'asset-1'], $result->getResponse());

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://cdn.prepr.io/assets'
                && $request->isMultipart();
        });
    }

    public function test_large_file_uses_chunked_upload_flow(): void
    {
        Http::fake([
            'cdn.prepr.io/assets' => Http::response(['id' => 'asset-99'], 201),
            'cdn.prepr.io/assets/asset-99/multipart' => Http::sequence()
                ->push(['ok' => true], 200)
                ->push(['ok' => true], 200)
                ->push(['ok' => true], 200)
                ->push(['ok' => true], 200),
        ]);

        $prepr = (new Prepr)
            ->path('assets')
            ->params(['body' => 'Example']);

        $chunkSize = new ReflectionProperty(Prepr::class, 'chunkSize');
        $chunkSize->setAccessible(true);
        $chunkSize->setValue($prepr, 10);

        $result = $prepr->file(str_repeat('a', 25), 'big.bin');

        $this->assertSame(200, $result->getStatusCode());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://cdn.prepr.io/assets'
                && $this->multipartValue($request, 'upload_phase') === 'start'
                && (int) $this->multipartValue($request, 'file_size') === 25;
        });

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/assets/asset-99/multipart'
            && $this->multipartValue($request, 'upload_phase') === 'transfer');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/assets/asset-99/multipart'
            && $this->multipartValue($request, 'upload_phase') === 'finish');

        Http::assertSentCount(5);
    }

    public function test_chunked_upload_stops_when_start_fails(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['message' => 'denied'], 403),
        ]);

        $prepr = (new Prepr)
            ->path('assets')
            ->params([]);

        $chunkSize = new ReflectionProperty(Prepr::class, 'chunkSize');
        $chunkSize->setAccessible(true);
        $chunkSize->setValue($prepr, 10);

        $result = $prepr->file(str_repeat('a', 25), 'big.bin');

        $this->assertSame(403, $result->getStatusCode());
        Http::assertSentCount(1);
    }

    protected function multipartValue(Request $request, string $name): mixed
    {
        foreach ($request->data() as $part) {
            if (is_array($part) && ($part['name'] ?? null) === $name) {
                return $part['contents'] ?? null;
            }
        }

        return data_get($request->data(), $name);
    }
}
