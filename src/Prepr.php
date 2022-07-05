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
    protected $query;
    protected $method;
    protected $params;
    protected $response;
    protected $rawResponse;
    protected $request;
    protected $authorization;
    protected $cache;
    protected $cacheTime;
    protected $statusCode;
    protected $userId;
    protected $attach;

    private $chunkSize = 26214400;

    public function __construct()
    {
        $this->cache = config('prepr.cache');
        $this->cacheTime = config('prepr.cache_time');
        $this->baseUrl = config('prepr.url');
        $this->authorization = config('prepr.token');
    }

    protected function client()
    {
        return Http::acceptJson()
            ->withToken($this->authorization)
            ->withHeaders(array_merge(config('prepr.headers'), [
                'Prepr-ABTesting' => $this->userId
            ]));
    }

    protected function request()
    {
        $this->url = $this->baseUrl . $this->path . ($this->query ? '?' . http_build_query($this->query) : '');

        $cacheHash = null;
        if ($this->method == 'get' && $this->cache) {

            $cacheHash = md5($url . $this->authorization . $this->userId);
            if (Cache::has($cacheHash)) {

                $data = Cache::get($cacheHash);

                $this->request = data_get($data, 'request');
                $this->response = data_get($data, 'response');

                return $this;
            }
        }

        $this->client = $this->client();

        if($this->attach) {
            $this->client->attach(key($this->attach), head($this->attach));
        }

        $this->request = $this->client->{$this->method}($this->url, $this->params);

        $this->rawResponse = $this->request->body();
        $this->response = json_decode($this->rawResponse, true);

        if ($this->cache) {
            $data = [
                'request' => $this->request,
                'response' => $this->response,
            ];
            Cache::put($cacheHash, $data, $this->cacheTime);
        }

        return $this;
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

        return $this;
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

    public function file($file)
    {
        $original = Utils::streamFor($file);
        $fileSize = $original->getSize();

        if ($fileSize > $this->chunkSize) {
            return $this->chunkUpload($original,$fileSize);
        } else {

            $this->attach = [
                'source' => $original
            ];

            return $this->post();

        }
    }

    private function chunkUpload($original, $fileSize)
    {
        $chunks = (int)floor($fileSize / $this->chunkSize);

        $this->params = [
            'upload_phase' => 'start',
            'file_size' => $fileSize
        ];

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
                'file_chunk' => $stream
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

    public function hashUserId($userId)
    {
        $hashValue = Murmur::hash3_int($userId, 1);
        $ratio = $hashValue / pow(2, 32);
        return intval($ratio*10000);
    }

    public function userId($userId)
    {
        $this->userId = $this->hashUserId($userId);

        return $this;
    }

    public function clearCache()
    {
        return Artisan::call('cache:clear');
    }
}