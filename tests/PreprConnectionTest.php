<?php

namespace Preprio\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Preprio\Prepr;

class PreprConnectionTest extends TestCase
{
    public function test_timeout_returns_504_without_throwing(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 30001 milliseconds');
        });

        $result = (new Prepr)
            ->path('publications')
            ->get();

        $this->assertSame(504, $result->getStatusCode());
        $this->assertSame('connection.error.timeout', data_get($result->getResponse(), 'message'));
        $this->assertSame(
            ['connection' => ['connection.error.timeout']],
            data_get($result->getResponse(), 'errors')
        );
    }

    public function test_connection_failure_returns_502_without_throwing(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 7: Failed to connect to host');
        });

        $result = (new Prepr)
            ->path('publications')
            ->get();

        $this->assertSame(502, $result->getStatusCode());
        $this->assertSame('connection.error.failed', data_get($result->getResponse(), 'message'));
    }
}
