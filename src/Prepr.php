<?php

namespace Preprio;

use GuzzleHttp\Client;
use Cache;
use Artisan;
use lastguest\Murmur;
use Session;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;

class Prepr
{
    protected $baseUrl;
    protected $path;
    protected $query;
    protected $rawQuery;
    protected $method;
    protected $params = [];
    protected $response;
    protected $rawResponse;
    protected $request;
    protected $authorization;
    protected $cache;
    protected $cacheTime;
    protected $file = null;
    protected $statusCode;
    protected $userId;

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
        return new Client([
            'http_errors' => false,
            'headers' => array_merge(config('prepr.headers'), [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => $this->authorization,
                'Prepr-ABTesting' => $this->userId
            ])
        ]);
    }

    protected function request($options = [])
    {
        $url = $this->baseUrl . $this->path;

        $cacheHash = null;
        if ($this->method == 'get' && $this->cache) {

            $cacheHash = md5($url . $this->authorization . $this->userId . $this->query);
            if (Cache::has($cacheHash)) {

                $data = Cache::get($cacheHash);

                $this->request = data_get($data, 'request');
                $this->response = data_get($data, 'response');

                return $this;
            }
        }

        $this->client = $this->client();

        $data = [
            'form_params' => $this->params,
        ];

        if ($this->path === 'graphql') {
            $data = [
                'json' => [
                    'query' => $this->params,
                ],
            ];
        } else if ($this->method == 'post') {
            $data = [
                'multipart' => $this->nestedArrayToMultipart($this->params),
            ];
        }

        $this->request = $this->client->request($this->method, $url . $this->query, $data);

        $this->rawResponse = $this->request->getBody()->getContents();
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

    public function authorization($authorization)
    {
        $this->authorization = $authorization;

        return $this;
    }

    public function url($url)
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

    public function path($path = null, array $array = [])
    {
        foreach ($array as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }

        $this->path = $path;

        return $this;
    }

    public function method($method = null)
    {
        $this->method = $method;

        return $this;
    }

    public function query(array $array)
    {
        $this->rawQuery = $array;
        $this->query = '?' . http_build_query($array);

        return $this;
    }

    public function params(array $array)
    {
        $this->params = $array;

        return $this;
    }

    public function graphQL(string $query)
    {
        $this->path = 'graphql';
        $this->params = $query;

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
            $this->chunkUpload($original);
        } else {
            data_set($this->params, 'source', $original);
        }

        return $this;
    }

    private function chunkUpload($original)
    {
        $fileSize = $original->getSize();
        $chunks = (int)floor($fileSize / $this->chunkSize);

        data_set($this->params, 'upload_phase', 'start');
        data_set($this->params, 'file_size', $fileSize);

        $start = (new self())
            ->authorization($this->authorization)
            ->path('assets')
            ->params($this->params)
            ->post();

        if ($start->getStatusCode() != 200 && $start->getStatusCode() != 201) {
            return $start;
        }

        $assetId = data_get($start->getResponse(), 'id');

        for ($i = 0; $i <= $chunks; $i++) {

            $offset = ($this->chunkSize * $i);
            $endOfFile = $i === $chunks - 1;
            $limit = ($endOfFile ? ($fileSize - $offset) : $this->chunkSize);

            $stream = new LimitStream($original, $limit, $offset);

            $params = [
                'upload_phase' => 'transfer',
                'file_chunk' => $stream
            ];

            $transfer = (new self())
                ->authorization($this->authorization)
                ->path('assets/{id}/multipart', [
                    'id' => $assetId,
                ])
                ->params($params)
                ->post();

            if ($transfer->getStatusCode() !== 200) {
                return $transfer;
            }
        }

        data_set($this->params, 'upload_phase', 'finish');

        return (new self())
            ->authorization($this->authorization)
            ->path('assets/{id}/multipart', [
                'id' => $assetId,
            ])
            ->params($this->params)
            ->post();
    }

    public function autoPaging()
    {
        $this->method = 'get';

        $perPage = 100;
        $page = 0;
        $queryLimit = data_get($this->rawQuery, 'limit');

        $arrayItems = [];

        while(true) {

            $query = $this->query;

            data_set($query,'limit', $perPage);
            data_set($query,'offset',$page*$perPage);

            $result = (new Prepr())
                ->authorization($this->authorization)
                ->path($this->path)
                ->query($query)
                ->get();

            if($result->getStatusCode() == 200) {

                $items = data_get($result->getResponse(),'items');
                if($items) {

                    foreach($items as $item) {
                        $arrayItems[] = $item;

                        if (count($arrayItems) == $queryLimit) {
                            break;
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

    public function nestedArrayToMultipart($array)
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