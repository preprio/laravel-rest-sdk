# Getting started with Laravel

This Laravel package is a provider for the Prepr API.

## Basics

- The SDK on [GitHub](https://github.com/preprio/laravel-sdk)  
- Compatible with Laravel `v5x`, `v6x`, `v7x`, `v8x`, `v9x`.
- Requires `GuzzleHttp 7.3.X`.

## Installation

You can install the Provider as a composer package.

For Laravel v9x

```bash
composer require preprio/laravel-sdk
```

For Laravel v8x

```bash
composer require preprio/laravel-sdk:"1.3"
```

Other versions

```bash
composer require preprio/laravel-sdk:"1.1"
```

### Publish config

Publish `prepr.php` config
```
php artisan vendor:publish --provider="Preprio\PreprServiceProvider"
```

## Set up your .env file configuration

You can set the default configuration in your .env file of you Laravel project.

```text
PREPR_URL=https://cdn.prepr.io/
PREPR_TOKEN={{ACCESS_TOKEN}}
```

## Laravel local caching

To make use of the caching feature of Laravel, add the following parameters to your .env file.

```text
PREPR_CACHE=true
PREPR_CACHE_TIME=1800
```

# GraphQL: Making your first request

```php
$apiRequest = (new Prepr)
    ->graphQL('{
    
    Posts {
        items {
            _id
            _slug
            title       
        }
    }
}');

if($apiRequest->getStatusCode() == 200) {
    dump($apiRequest->getResponse());
}
```

Check out the [GraphQL docs](https://docs.prepr.io/reference/graphql/v1/overview) on how to query the API.

## REST: Making your first request

Let's start with getting all content items from your Prepr Environment.

```php
<?php

use Preprio\Prepr;

$apiRequest = new Prepr;

$apiRequest
    ->path('publications')
    ->query([
        'fields' => 'items'
    ])
    ->get();

if($apiRequest->getStatusCode() == 200) {

    print_r($apiRequest->getResponse());
}
```


To get a single content item, pass the Id to the request.

```php
<?php

use Preprio\Prepr;

$apiRequest = new Prepr;

$apiRequest
    ->path('publications/{id}', [
        'id' => '1236f0b1-b26d-4dde-b835-9e4e441a6d09'
    ])
    ->query([
        'fields' => 'items'
    ])
    ->get();

if($apiRequest->getStatusCode() == 200) {

    print_r($apiRequest->getResponse());
}
```

## A/B testing

To enable A/B testing you can pass a User ID to provide a consistent result.
The A/B testing feature requires the use of the cached CDN API.

To switch off A/B testing, pass NULL to the UserId param.

```php
$apiRequest = new Prepr( '{{YourCustomUserId}}');
```

or per request

```php
$apiRequest
    ->path('publications/{id}',[
        'id' => 1
    ]),
    ->query([
        'fields' => 'example'
    ])
    ->userId(
        session()->getId() // For Example you can use Laravel's Session ID.
    )
    ->get();

if($apiRequest->getStatusCode() == 200) {

    print_r($apiRequest->getResponse());
}
```

For more information check the [A/B testing documentation](https://docs.prepr.io/docs/digging-deeper/v1/ab-testing).


### Override the AccessToken in a request

The authorization can also be set for one specific request `->url('url')->authorization('token')`.

## Autopaging

```php
$apiRequest = (new Prepr)
    ->path('publications')
    ->query([
        'limit' => 200 // optional
    ])
    ->autoPaging();

if($apiRequest->getStatusCode() == 200) {
    dump($apiRequest->getResponse());
}
```

## Create, Update & Destroy

### Post

```php
$apiRequest = (new Prepr)
    ->path('publications')
    ->params([
        'body' => 'Example'
    ])
    ->post();

if($apiRequest->getStatusCode() == 201) {
    dump($apiRequest->getResponse());
}
```

### Put (Update)

```php
$apiRequest = (new Prepr)
    ->path('publications')
    ->params([
        'body' => 'Example'
    ])
    ->put();

if($apiRequest->getStatusCode() == 200) {
    dump($apiRequest->getResponse());
}
```

### Delete

```php
$apiRequest = (new Prepr)
    ->path('publications/{id}',[
        'id' => 1
    ])
    ->delete();

if($apiRequest->getStatusCode() == 204) {
    // Deleted.
}
```

### Multipart/Chunk upload

- Option 1
```php
use Illuminate\Support\Facades\Storage;

$source = Storage::readStream('image.jpg');

$apiRequest = (new Prepr)
    ->path('assets')
    ->params([
      'body' => 'Example',
    ])
    ->file($source);

if($apiRequest->getStatusCode() == 200) {
    dump($apiRequest->getResponse());
}
```
- Option 2
```php
use Illuminate\Support\Facades\Storage;

$source = Storage::get('image.jpg');

$apiRequest = (new Prepr)
    ->path('assets')
    ->params([
      'body' => 'Example',
    ])
    ->file($source, 'image.jpg');

if($apiRequest->getStatusCode() == 200) {
    dump($apiRequest->getResponse());
}
```

### Debug

For debug you can use `getRawResponse()`
