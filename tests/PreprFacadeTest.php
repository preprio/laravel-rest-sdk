<?php

namespace Preprio\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Preprio\Prepr;
use Preprio\PreprFacade as PreprFacadeAlias;

class PreprFacadeTest extends TestCase
{
    protected function getPackageAliases($app): array
    {
        return [
            'Prepr' => PreprFacadeAlias::class,
        ];
    }

    public function test_container_binding_resolves_prepr_instance(): void
    {
        $this->assertInstanceOf(Prepr::class, $this->app->make('prepr'));
    }

    public function test_facade_can_perform_requests(): void
    {
        Http::fake([
            'cdn.prepr.io/*' => Http::response(['via' => 'facade'], 200),
        ]);

        $result = \Prepr::path('publications')->get();

        $this->assertSame(['via' => 'facade'], $result->getResponse());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.prepr.io/publications');
    }
}
