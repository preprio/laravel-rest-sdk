<?php

namespace Preprio\Tests;

use Preprio\Prepr;

class PreprMultipartTest extends TestCase
{
    public function test_nested_array_is_flattened_to_multipart_fields(): void
    {
        $result = (new Prepr)->nestedArrayToMultipart([
            'body' => 'Example',
            'meta' => [
                'locale' => 'nl-NL',
                'flags' => [
                    'draft' => '1',
                ],
            ],
        ]);

        $this->assertSame([
            ['name' => 'body', 'contents' => 'Example'],
            ['name' => 'meta[locale]', 'contents' => 'nl-NL'],
            ['name' => 'meta[flags][draft]', 'contents' => '1'],
        ], $result);
    }

    public function test_empty_array_returns_empty_multipart_list(): void
    {
        $this->assertSame([], (new Prepr)->nestedArrayToMultipart([]));
    }
}
