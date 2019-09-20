<?php

namespace LeadThread\Bitly;

use LeadThread\Bitly\Exceptions\BitlyAuthException;
use LeadThread\Bitly\Exceptions\BitlyErrorException;
use LeadThread\Bitly\Exceptions\BitlyRateLimitException;
use GuzzleHttp\Client;
use React\EventLoop\LoopInterface;

class Bitly
{
    const V3 = 'v3';

    protected $host;
    protected $version;
    protected $client;
    protected $token;
    protected $loop;
    /**
     * Creates a Bitly instance that can register and unregister webhooks with the API
     * @param string $token   The API token to authenticate with
     * @param string $version The API version to use
     * @param string $host    The Host URL
     * @param string $client  The Client instance that will handle the http request
     */
    public function __construct(LoopInterface $loop,$token, $version = self::V3, $host = "api-ssl.bitly.com", Client $client = null){
        $this->client = $client;
        $this->loop = $loop;
        $this->token = $token;
        $this->version = $version;
        $this->host = $host;
    }

    public function shorten($url, $encode = true)
    {
        if (empty($url)) {
            throw new BitlyErrorException("The URL is empty!");
        }

        $url = $this->fixUrl($url, $encode);
        $deferred = new \React\Promise\Deferred();

        $this->exec($this->buildRequestUrl($url))->done(function($data)use($deferred){
            $deferred->resolve($data['data']['url']);
        });
        $promise = $deferred->promise();
        return $promise;
    }

    /**
     * Returns the response data or throws an Exception if it was unsuccessful
     * @param  string $raw The data from the response
     * @return array
     */
    protected function handleResponse($raw){
        $data = json_decode($raw,true);

        if(!isset($data['status_code'])){
            return $raw;
        }

        if($data['status_code']>=300 || $data['status_code']<200){
            switch ($data['status_txt']) {
                case 'RATE_LIMIT_EXCEEDED':
                    throw new BitlyRateLimitException;
                    break;

                case 'INVALID_LOGIN':
                    throw new BitlyAuthException;
                    break;

                default:
                    throw new BitlyErrorException($data['status_txt']);
                    break;
            }
        }
        return $data;
    }

    /**
     * Returns a corrected URL
     * @param  string  $url    The URL to modify
     * @param  boolean $encode Whether or not to encode the URL
     * @return string          The corrected URL
     */
    protected function fixUrl($url, $encode){
        if(strpos($url, "http") !== 0){
            $url = "http://".$url;
        }

        if($encode){
            $url = urlencode($url);
        }

        return $url;
    }

    /**
     * Builds the request URL to the Bitly API for a specified action
     * @param  string $action The long URL
     * @param  string $action The API action
     * @return string         The URL
     */
    protected function buildRequestUrl($url,$action = "shorten"){
        return "https://{$this->host}/{$this->version}/{$action}?access_token={$this->token}&format=json&longUrl={$url}";
    }

    /**
     * Returns the Client instance
     * @return Client
     */
    protected function getRequest(){
        $client = $this->client;
        if(!$client instanceof Client){
            $handler = new \WyriHaximus\React\GuzzlePsr7\HttpClientAdapter($this->loop);
            $client = new Client([
                'handler' => \GuzzleHttp\HandlerStack::create($handler)
            ]);
        }
        return $client;
    }

    /**
     * Executes a CURL request to the Bitly API
     * @param  string $url    The URL to send to
     * @return mixed          The response data
     */ 
    protected function exec($url)
    {
        $client = $this->getRequest();
        $deferred = new \React\Promise\Deferred();

       
        $response = $client->getAsync($url)->then(\Closure::bind(function($response)use($deferred){
            $deferred->resolve($this->handleResponse($response->getBody()));
        },$this));
        $promise = $deferred->promise();
        return $promise;
    }
}
