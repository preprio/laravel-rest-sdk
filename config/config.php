<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Base Url
    |--------------------------------------------------------------------------
    |
    | The base url of the API to consume.
    |
    */

    'url' => env('PREPR_URL'),

    /*
    |--------------------------------------------------------------------------
    | Token
    |--------------------------------------------------------------------------
    |
    | The token of the API to consume.
    |
    */

    'token' => env('PREPR_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Turn the cache on or off.
    |
    */

    'cache' => env('PREPR_CACHE', false),

    /*
    |--------------------------------------------------------------------------
    | Cache Time in seconds
    |--------------------------------------------------------------------------
    |
    | Default 5 minutes (300 seconds)
    |
    */

    'cache_time' => env('PREPR_CACHE_TIME', 300),

    /*
    |--------------------------------------------------------------------------
    | HTTP Headers
    |--------------------------------------------------------------------------
    |
    | The HTTP headers to be send along in each call
    |
    */

    'headers' => [

    ]

];