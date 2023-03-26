<?php

namespace Usamamuneerchaudhary\LaraClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Psr\Http\Message\ResponseInterface;
use Usamamuneerchaudhary\LaraClient\Contracts\LaraClientInterface;
use Usamamuneerchaudhary\LaraClient\Exceptions\LaraClientApiClientException;
use Usamamuneerchaudhary\LaraClient\Models\LaraClientLog;

class LaraClient implements LaraClientInterface
{
    protected $httpClient;
    protected $config;

    /**
     * @param $connection
     */
    public function __construct($connection = null)
    {
        $this->config = Config::get('lara_client.connections.'.($connection ?: Config::get('lara_client.default')));
        $this->httpClient = new Client([
            'base_uri' => $this->config['base_uri'],
            'headers' => $this->config['default_headers'],
            'timeout' => $this->config['timeout'],
        ]);
    }

    /**
     * @param $uri
     * @param $queryParams
     * @return Response
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function get($uri, $queryParams = [])
    {
        $fullUrl = $this->getFullUrl($uri);
        return $this->request('GET', $fullUrl, ['query' => $queryParams]);
    }

    /**
     * @param $uri
     * @param $data
     * @return Response
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function post($uri, $data = [])
    {
        $fullUrl = $this->getFullUrl($uri);
        return $this->request('POST', $fullUrl, ['json' => $data]);
    }

    /**
     * @param $uri
     * @param $data
     * @return Response
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function put($uri, $data = [])
    {
        $fullUrl = $this->getFullUrl($uri);
        return $this->request('PUT', $fullUrl, ['json' => $data]);
    }

    /**
     * @param $uri
     * @param $data
     * @return Response
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function patch($uri, $data = [])
    {
        $fullUrl = $this->getFullUrl($uri);
        return $this->request('PATCH', $fullUrl, ['json' => $data]);
    }

    /**
     * @param $uri
     * @param $data
     * @return Response
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function delete($uri, $data = [])
    {
        $fullUrl = $this->getFullUrl($uri);
        return $this->request('DELETE', $fullUrl, ['json' => $data]);
    }

    /**
     * @throws LaraClientApiClientException
     * @throws GuzzleException
     */
    protected function request($method, $uri, $options)
    {
        $options['headers'] = $this->getHeaders();

        if (Cache::has('api_rate_limit')) {
            sleep($this->config['rate_limit']['interval']);
        }

        try {
            $response = $this->httpClient->request($method, $uri, $options);
            $this->logRequest($method, $uri, $options, $response);
            $this->handleRateLimit($response->getHeader('X-RateLimit-Reset'));
        } catch (RequestException $e) {
            $response = $e->getResponse();

            if ($response->getStatusCode() === 429) {
                $this->handleRateLimit($response->getHeader('X-RateLimit-Reset'));
                return $this->request($method, $uri, $options);
            }
            throw new LaraClientApiClientException($response->getStatusCode(), $response->getReasonPhrase());
        }
        return new Response($response->getStatusCode(), $response->getBody());
    }

    /**
     * @param $additionalHeaders
     * @return array
     */
    protected function getHeaders($additionalHeaders = [])
    {
        // Merge the default headers with any additional headers passed in
        $headers = array_merge($this->config['default_headers'], $additionalHeaders);

        // Add the Authorization header if an API key is set
        if (!empty($this->config['api_key'])) {
            $headers['Authorization'] = 'Bearer '.$this->config['api_key'];
        }

        return $headers;
    }

    /**
     * @param $resetHeader
     * @return void
     */
    protected function handleRateLimit($resetHeader)
    {
        if (!empty($resetHeader)) {
            $resetTimestamp = (int) $resetHeader[0];
            $currentTimestamp = time();

            if ($resetTimestamp > $currentTimestamp) {
                $waitTime = $resetTimestamp - $currentTimestamp;
                Cache::put('api_rate_limit', true, $waitTime);
            }
        }
    }


    /**
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $options
     * @param $response
     * @return void
     */
    protected function logRequest(string $method, string $uri, array $options, $response)
    {
        $status = $response instanceof ResponseInterface ? $response->getStatusCode() : null;
        $responseBody = $response instanceof ResponseInterface ? (string) $response->getBody() : null;

        LaraClientLog::create([
            'endpoint' => $uri,
            'method' => $method,
            'request_payload' => json_encode($options),
            'response_status' => $status,
            'response_body' => $responseBody,
            'created_at' => now()
        ]);
    }

    /**
     * @param $uri
     * @return string
     */
    protected function getFullUrl($uri)
    {
        $fullUrl = $uri;
        if (!preg_match('/^https?:\/\//', $uri)) {
            $fullUrl = $this->config['base_uri'].$uri;
        }
        return $fullUrl;
    }
}
