<?php

namespace Preprio\Tests;

use Illuminate\Support\Facades\Cache;
use Preprio\Prepr;

class PreprClearCacheTest extends TestCase
{
    public function test_clear_cache_flushes_laravel_cache(): void
    {
        Cache::put('example-key', 'value', 60);

        $this->assertTrue(Cache::has('example-key'));

        (new Prepr)->clearCache();

        $this->assertFalse(Cache::has('example-key'));
    }
}
