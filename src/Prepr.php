<?php

namespace Preprio;

use Cache;
use Artisan;
use lastguest\Murmur;
use Session;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;

class Prepr
{
    protected $baseUrl;
    protected $url;
    protected $path;
    protected $query = [];
    protected $headers = [];
    protected $method;
    protected $params;
    protected $response;
    protected $rawResponse;
    protected $request;
    protected $authorization;
    protected $cache;
    protected $cacheTime;
    protected $cacheTag;
    protected $statusCode;
    protected $userId;
    protected $attach;

    private $chunkSize = 26214400;

    public function __construct()
    {
        $this->cache = config('prepr.cache');
        $this->cacheTime = config('prepr.cache_time');
        $this->cacheTag = config('prepr.cache_tag');
        $this->baseUrl = config('prepr.url');
        $this->authorization = config('prepr.token');
    }

    protected function client()
    {
        $headers = array_merge(config('prepr.headers'), $this->headers);

        if($this->userId) {
            $headers['Prepr-ABTesting'] = $this->userId;
        }

        return Http::acceptJson()
            ->withToken($this->authorization)
            ->withHeaders($headers);
    }

    protected function request()
    {
        $this->url = $this->baseUrl . $this->path . ($this->query ? '?' . http_build_query($this->query) : '');

        $cacheHash = null;
        if ($this->method == 'get' && $this->cache) {

            $cacheHash = md5($this->url . $this->authorization . $this->userId);

            $explode = explode( '/',$this->path);

            $cacheData = Cache::tags([
                $this->cacheTag,
                data_get($explode,0)
            ])->get($cacheHash);

            if ($cacheData) {

                $this->request = data_get($cacheData, 'request');
                $this->response = data_get($cacheData, 'response');

                return $this;
            }
        }

        $this->client = $this->client();

        if($this->attach) {

            //Fix for laravel bug https://github.com/laravel/framework/issues/43710
            data_set($this->params, data_get($this->attach,'name'), data_get($this->attach,'contents'));
            //End fix for laravel

            //$this->client->attach(data_get($this->attach,'name'), data_get($this->attach,'contents'), data_get($this->attach,'filename'));
        }

        $data = $this->params;

        //Fix for laravel bug https://github.com/laravel/framework/issues/43710
        if ($this->method == 'post') {

            $this->client->asMultipart();

            if($this->params) {
                $data = $this->nestedArrayToMultipart($this->params);
            }
        }
        //End fix for laravel

        $this->removeCache();

        $this->request = $this->client->{$this->method}($this->url, $data);

        $this->rawResponse = $this->request->body();
        $this->response = json_decode($this->rawResponse, true);

        //Cache::tags('authors')->flush();

        if ($this->method == 'get' && $this->cache) {
            $data = [
                'request' => $this->request,
                'response' => $this->response,
            ];

            $explode = explode( '/',$this->path);

            Cache::tags([
                $this->cacheTag,
                data_get($explode,0)
            ])->put($cacheHash, $data, $this->cacheTime);

//            Cache::put($cacheHash, $data, $this->cacheTime);
        }

        return $this;
    }

    public function removeCache()
    {
        if(in_array($this->method, [
            'post',
            'put',
            'delete',
        ])) {

            $explode = explode($this->path,'/');

            Cache::tags([
                $this->cacheTag,
                data_get($explode,0)
            ])->flush();
        }
    }

    public function authorization(string $authorization)
    {
        $this->authorization = $authorization;

        return $this;
    }

    public function url(string $url)
    {
        $this->baseUrl = $url;

        return $this;
    }

    public function headers(array $headers)
    {
        $this->headers = $headers;

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

    public function cached()
    {
        $this->cache = true;

        return $this;
    }

    public function path(string $path = null, array $array = [])
    {
        foreach ($array as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }

        $this->path = $path;

        return $this;
    }

    public function method(string $method = null)
    {
        $this->method = $method;

        return $this;
    }

    public function query(array $array)
    {
        $this->query = $array;

        return $this;
    }

    public function params(array $array)
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

    public function getStatusCode()
    {
        if($this->statusCode) {
            return $this->statusCode;
        }
        return $this->request->getStatusCode();
    }

    public function file($file, string $filename = null)
    {
        $original = Utils::streamFor($file);
        $fileSize = $original->getSize();

        if ($fileSize > $this->chunkSize) {
            return $this->chunkUpload($original,$fileSize);
        } else {

            $this->attach = [
                'name' => 'source',
                'contents' => $original,
                'filename' => $filename
            ];

            return $this->post();

        }
    }

    private function chunkUpload($original, int $fileSize, string $filename = null)
    {
        $chunks = (int)floor($fileSize / $this->chunkSize);

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

            $this->path('assets/' . $assetId);

            $this->params = [
                'upload_phase' => 'transfer'
            ];

            $this->attach = [
                'name' => 'file_chunk',
                'contents' => $stream,
                'filename' => $filename
            ];

            $transfer = $this->post();
            if ($transfer->getStatusCode() !== 200) {
                return $transfer;
            }
        }

        $this->params = [
            'upload_phase' => 'finish'
        ];

        $this->post();

        return $this;
    }

    public function autoPaging()
    {
        $perPage = 100;
        $page = 0;
        $queryLimit = data_get($this->query, 'limit');

        $arrayItems = [];

        while(true) {

            $queryOffset = data_get($this->query, 'offset');

            $this->query = array_merge($this->query,[
                'limit' => $perPage,
                'offset' => ($queryOffset ? $queryOffset + ($page*$perPage) : $page*$perPage)
            ]);

            $result = $this->get();
            if($result->getStatusCode() == 200) {

                $items = data_get($result->getResponse(),'items');
                if($items) {

                    foreach($items as $item) {
                        $arrayItems[] = $item;

                        if (count($arrayItems) == $queryLimit) {
                            break 2;
                        }
                    }

                    if(count($items) == $perPage) {
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
            'total' => count($arrayItems)
        ];
        $this->statusCode = 200;

        return $this;
    }

    public function hashUserId(string $userId)
    {
        $hashValue = Murmur::hash3_int($userId, 1);
        $ratio = $hashValue / pow(2, 32);
        return intval($ratio*10000);
    }

    public function userId(string $userId)
    {
        $this->userId = $this->hashUserId($userId);

        return $this;
    }

    public function nestedArrayToMultipart(array $array)
    {
        $flatten = function ($array, $original_key = '') use (&$flatten) {
            $output = [];
            foreach ($array as $key => $value) {
                $new_key = $original_key;
                if (empty($original_key)) {
                    $new_key .= $key;
                } else {
                    $new_key .= '[' . $key . ']';
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
        return Artisan::call('cache:clear');
    }
}
