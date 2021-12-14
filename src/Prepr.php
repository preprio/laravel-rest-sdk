<?php

namespace Preprio;

use GuzzleHttp\Client;
use Cache;
use Artisan;
use lastguest\Murmur;
use Session;

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
    protected $personalisation;
    protected $file = null;
    protected $statusCode;
    protected $customerId;

    private $chunkSize = 26214400;

    public function __construct()
    {
        $this->cache = config('prepr.cache');
        $this->cacheTime = config('prepr.cache_time');
        $this->baseUrl = config('prepr.url');
        $this->authorization = config('prepr.token');
        $this->personalisation = config('prepr.personalisation');
    }

    protected function client()
    {
        $headers = array_merge(config('prepr.headers'), [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authorization
        ]);

        if($this->customerId) {

            if($this->personalisation) {
                $headers['Prepr-Customer-Id'] = $this->customerId;
            } else {
                $headers['Prepr-Customer-Reference-Id'] = $this->customerId;
            }
        }

        return new Client([
            'http_errors' => false,
            'headers' => $headers
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

        if ($this->method == 'post') {
            $data = [
                'multipart' => $this->nestedArrayToMultipart($this->params),
            ];
        }

        $this->request = $this->client->request($this->method, $url . $this->query, $data);

        $this->rawResponse = $this->request->getBody()->getContents();
        $this->response = json_decode($this->rawResponse, true);


        // Files larger then 25 MB (upload chunked)
        if (data_get($this->file, 'chunks') > 1 && ($this->getStatusCode() === 201 || $this->getStatusCode() === 200)) {
            return $this->processFileUpload();
        }

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

    public function file($filepath)
    {
        $fileSize = filesize($filepath);
        $file = fopen($filepath, 'r');

        $this->file = [
            'path' => $filepath,
            'size' => $fileSize,
            'file' => $file,
            'chunks' => ($fileSize / $this->chunkSize),
            'original_name' => basename($filepath),
        ];

        if ($this->file) {

            // Files larger then 25 MB (upload chunked)
            if (data_get($this->file, 'chunks') > 1) {
                data_set($this->params, 'upload_phase', 'start');
                data_set($this->params, 'file_size', data_get($this->file, 'size'));

                // Files smaller then 25 MB (upload directly)
            } else {
                data_set($this->params, 'source', data_get($this->file, 'file'));
            }

        }

        return $this;
    }

    private function processFileUpload()
    {
        $id = data_get($this->response, 'id');
        $fileSize = data_get($this->file, 'size');

        for ($i = 0; $i <= data_get($this->file, 'chunks'); $i++) {

            $offset = ($this->chunkSize * $i);
            $endOfFile = (($offset + $this->chunkSize) > $fileSize ? true : false);

            $original = \GuzzleHttp\Psr7\stream_for(data_get($this->file, 'file'));
            $stream = new \GuzzleHttp\Psr7\LimitStream($original, ($endOfFile ? ($fileSize - $offset) : $this->chunkSize), $offset);

            data_set($this->params, 'upload_phase', 'transfer');
            data_set($this->params, 'file_chunk', $stream);

            $authorization = $this->authorization;

            $prepr = (new self())
                ->authorization($authorization)
                ->path('assets/{id}/multipart', [
                    'id' => $id,
                ])
                ->params($this->params)
                ->post();

            if ($prepr->getStatusCode() !== 200) {
                return $prepr;
            }
        }

        data_set($this->params, 'upload_phase', 'finish');

        return (new self())
            ->authorization($authorization)
            ->path('assets/{id}/multipart', [
                'id' => $id,
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

    public function customerId($customerId)
    {
        $this->customerId = $customerId;

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