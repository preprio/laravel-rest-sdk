<?php

namespace Preprio;

use Cache;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Prepr
{
    // Request
    protected object $client;
    protected string $baseUrl;
    protected string $authorization;
    protected string $url;
    protected string $path;
    protected string $method;
    protected Response $request;
    protected mixed $query = [];
    protected mixed $params = [];
    protected null|array $attach = null;

    // Settings
    protected bool $cache;
    protected int $cacheTime;
    protected int $chunkSize = 26214400;

    // Reponse
    protected array $response;
    protected string $rawResponse;
    protected int|null $statusCode = null;

    // Customer ID for personalization
    protected mixed $customerId = null;

    public function __construct()
    {
        $this->cache = config('prepr.cache', false);
        $this->cacheTime = config('prepr.cache_time', 3600);
        $this->baseUrl = config('prepr.url');
        $this->authorization = config('prepr.token');
    }

    protected function client()
    {
        return Http::acceptJson()
            ->withToken($this->authorization)
            ->withHeaders(array_merge(config('prepr.headers'), [
                'Prepr-Customer-Id' => $this->customerId,
            ]));
    }

    protected function request()
    {
        $this->url = $this->baseUrl.$this->path.($this->query ? '?'.http_build_query($this->query) : '');

        // Use Laravel Cache if this is requested.
        $cacheHash = null;
        if ($this->method == 'get' && $this->cache) {
            $cacheHash = md5($this->url.$this->authorization.$this->customerId);
            if (Cache::has($cacheHash)) {
                $data = Cache::get($cacheHash);

                $this->request = data_get($data, 'request');
                $this->response = data_get($data, 'response');

                return $this;
            }
        }

        // Get HTTP Client.
        $this->client = $this->client();

        if ($this->attach) {

            // Fix for Laravel bug https://github.com/laravel/framework/issues/43710
            data_set($this->params, data_get($this->attach, 'name'), data_get($this->attach, 'contents'));
            // End fix for Laravel

            // $this->client->attach(data_get($this->attach,'name'), data_get($this->attach,'contents'), data_get($this->attach,'filename'));
        }

        // Set params as request body.
        $data = $this->params;

        // Fix for Laravel bug https://github.com/laravel/framework/issues/43710
        if ($this->method == 'post') {
            $this->client->asMultipart();

            if ($this->params) {
                $data = $this->nestedArrayToMultipart($this->params);
            }
        }
        // End fix for Laravel

        $this->request = $this->client->{$this->method}($this->url, $data);

        $this->rawResponse = $this->request->body();
        $this->response = json_decode($this->rawResponse, true);

        // If caching is enabled, save it to the cache.
        if ($this->cache) {
            $data = [
                'request' => $this->request,
                'response' => $this->response,
            ];

            Cache::put($cacheHash, $data, $this->cacheTime);
        }

        return $this;
    }

    public function authorization(string $authorization): self
    {
        $this->authorization = $authorization;

        return $this;
    }

    public function url(string $url): self
    {
        $this->baseUrl = $url;

        return $this;
    }

    public function get()
    {
        $this->method = 'get';

        return $this->request();
    }

    public function post()
    {
        $this->method = 'post';

        return $this->request();
    }

    public function put()
    {
        $this->method = 'put';

        return $this->request();
    }

    public function delete()
    {
        $this->method = 'delete';

        return $this->request();
    }

    public function path(string $path = null, array $array = [])
    {
        foreach ($array as $key => $value) {
            $path = str_replace('{'.$key.'}', $value, $path);
        }

        $this->path = $path;

        return $this;
    }

    public function method(string $method = null): self
    {
        $this->method = $method;

        return $this;
    }

    public function query(array $array): self
    {
        $this->query = $array;

        return $this;
    }

    public function params(array $array): self
    {
        $this->params = $array;

        return $this;
    }

    public function graphQL(string $query, array $variables = [])
    {
        $this->path = 'graphql';
        $this->params['query'] = $query;
        $this->params['variables'] = $variables;

        return $this->post();
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    public function getStatusCode(): int
    {
        if ($this->statusCode) {
            return $this->statusCode;
        }

        return $this->request->getStatusCode();
    }

    public function file($file, string $filename = null)
    {
        $original = Utils::streamFor($file);
        $fileSize = $original->getSize();

        if ($fileSize > $this->chunkSize) {
            return $this->chunkUpload($original, $fileSize);
        } else {
            $this->attach = [
                'name' => 'source',
                'contents' => $original,
                'filename' => $filename,
            ];

            return $this->post();
        }
    }

    private function chunkUpload($original, int $fileSize, string $filename = null)
    {
        $chunks = (int) floor($fileSize / $this->chunkSize);

        $this->params['upload_phase'] = 'start';
        $this->params['file_size'] = $fileSize;

        $start = $this->post();
        if ($start->getStatusCode() != 200 && $start->getStatusCode() != 201) {
            return $start;
        }

        $assetId = data_get($start->getResponse(), 'id');

        for ($i = 0; $i <= $chunks; $i++) {
            $offset = ($this->chunkSize * $i);
            $endOfFile = $i === $chunks;
            $limit = ($endOfFile ? ($fileSize - $offset) : $this->chunkSize);

            $stream = new LimitStream($original, $limit, $offset);

            $this->path('assets/'.$assetId);

            $this->params = [
                'upload_phase' => 'transfer',
            ];

            $this->attach = [
                'name' => 'file_chunk',
                'contents' => $stream,
                'filename' => $filename,
            ];

            $transfer = $this->post();
            if ($transfer->getStatusCode() !== 200) {
                return $transfer;
            }
        }

        $this->params = [
            'upload_phase' => 'finish',
        ];

        $this->post();

        return $this;
    }

    public function autoPaging()
    {
        // Set number of items per page.
        $perPage = 100;

        // Start with page 0.
        $page = 0;
        $queryLimit = data_get($this->query, 'limit');
        $queryOffset = data_get($this->query, 'offset');

        $arrayItems = [];

        while (true) {
            $this->query = array_merge($this->query, [
                'limit' => $perPage,
                'offset' => ($queryOffset ? $queryOffset + ($page * $perPage) : $page * $perPage),
            ]);

            // Reset offset after use.
            $queryOffset = null;

            $result = $this->get();
            if ($result->getStatusCode() == 200) {
                $items = data_get($result->getResponse(), 'items');
                if ($items) {
                    foreach ($items as $item) {
                        $arrayItems[] = $item;

                        if (count($arrayItems) == $queryLimit) {
                            break 2;
                        }
                    }

                    if (count($items) == $perPage) {
                        $page++;
                        continue;
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            } else {
                return $result;
            }
        }

        $this->response = [
            'items' => $arrayItems,
            'total' => count($arrayItems),
        ];

        $this->statusCode = 200;

        return $this;
    }

    public function userId(string $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function customerId(string $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function nestedArrayToMultipart(array $array): array
    {
        $flatten = function ($array, $original_key = '') use (&$flatten) {
            $output = [];
            foreach ($array as $key => $value) {
                $new_key = $original_key;
                if (empty($original_key)) {
                    $new_key .= $key;
                } else {
                    $new_key .= '['.$key.']';
                }

                if (is_array($value)) {
                    $output = array_merge($output, $flatten($value, $new_key));
                } else {
                    $output[$new_key] = $value;
                }
            }

            return $output;
        };

        $flat_array = $flatten($array);

        $multipart = [];
        foreach ($flat_array as $key => $value) {
            $multipart[] = [
                'name' => $key,
                'contents' => $value,
            ];
        }

        return $multipart;
    }

    public function clearCache()
    {
        return Cache::flush();
    }
}
