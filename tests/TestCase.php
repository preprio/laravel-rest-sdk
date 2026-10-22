<?php

namespace Preprio\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Preprio\PreprServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PreprServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('prepr.url', 'https://cdn.prepr.io/');
        $app['config']->set('prepr.token', 'test-token');
        $app['config']->set('prepr.cache', false);
        $app['config']->set('prepr.cache_time', 3600);
        $app['config']->set('prepr.timeout', 30);
        $app['config']->set('prepr.connect_timeout', 10);
        $app['config']->set('prepr.headers', []);
    }
}
