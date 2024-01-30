<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API URL to the Prepr API
    |--------------------------------------------------------------------------
    |
    | The base url of the API to consume.
    |
    */

    'url' => env('PREPR_URL'),

    /*
    |--------------------------------------------------------------------------
    | Access Token (for GraphQL requests this can be left empty)
    |--------------------------------------------------------------------------
    |
    | The access token of the API to consume.
    |
    */

    'token' => env('PREPR_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Cache Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable caching
    | of individual requests, which simply provides a single
    | and convenient way to enable or disable the use of
    | Laravel build-in caching.
    |
    */

    'cache' => env('PREPR_CACHE', false),

    /*
    |--------------------------------------------------------------------------
    | Cache Time in seconds
    |--------------------------------------------------------------------------
    |
    | Default 60 minutes (3600 seconds).
    |
    */

    'cache_time' => env('PREPR_CACHE_TIME', 3600),

    /*
    |--------------------------------------------------------------------------
    | Timeout in seconds
    |--------------------------------------------------------------------------
    |
    | Default 30 seconds.
    |
    */

    'timeout' => env('PREPR_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | ConnectTimeout in seconds
    |--------------------------------------------------------------------------
    |
    | Default 10 seconds.
    |
    */

    'connect_timeout' => env('PREPR_CONNECT_TIMEOUT', 10),
    /*
    |--------------------------------------------------------------------------
    | HTTP Headers
    |--------------------------------------------------------------------------
    |
    | The HTTP headers to be send along in each call
    |
    */

    'headers' => [
        //
    ],
];
