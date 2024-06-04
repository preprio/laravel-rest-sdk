# Laravel provider for the Prepr REST API

This Laravel package is a provider for the Prepr REST API.

## Basics

- The SDK on [GitHub](https://github.com/preprio/laravel-rest-sdk)  
- Compatible with Laravel `v5x`, `v6x`, `v7x`, `v8x`, `v9x`, `v10x`, `v11x`.
- Requires `GuzzleHttp 7.3.X`, and for version 3.0 and above PHP 8.x is required.

## Installation

You can install the Provider as a composer package.

For Laravel v10x and Laravel v11x

```bash
composer require preprio/laravel-rest-sdk:"^4.0"
```

For Laravel v9x

```bash
composer require preprio/laravel-rest-sdk:"^2.0"
```

For Laravel v8x

```bash
composer require preprio/laravel-rest-sdk:"^1.3"
```

Other versions

```bash
composer require preprio/laravel-rest-sdk:"1.1"
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

## Optional options

```text
PREPR_TIMEOUT=30
PREPR_CONNECT_TIMEOUT=10
```

## Making your first request

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
