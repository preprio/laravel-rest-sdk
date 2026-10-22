<?php

namespace Preprio\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Preprio\Prepr;

class PreprPathTest extends TestCase
{
    public function test_required_path_placeholders_are_replaced(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->path('publications/{id}', ['id' => 'abc-123'])
            ->get();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/publications/abc-123');
    }

    public function test_optional_path_placeholders_are_omitted_when_null(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->path('tags/{id?}', ['id' => null])
            ->get();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/tags');
    }

    public function test_optional_path_placeholders_are_kept_when_provided(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->path('tags/{id?}', ['id' => 'tag-1'])
            ->get();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/tags/tag-1');
    }

    public function test_empty_string_is_treated_as_provided_path_value(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->path('tags/{id}', ['id' => ''])
            ->get();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/tags');
    }

    public function test_missing_required_placeholder_logs_warning_and_continues(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context = []) {
                return str_contains($message, "Missing value for required path placeholder '{id}'")
                    && ($context['placeholder'] ?? null) === 'id';
            });

        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->path('publications/{id}', [])
            ->get();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/publications');
    }

    public function test_trailing_slashes_are_normalized(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['ok' => true], 200),
        ]);

        (new Prepr)
            ->path('publications/{id}/', ['id' => 'abc'])
            ->get();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/publications/abc');
    }
}
