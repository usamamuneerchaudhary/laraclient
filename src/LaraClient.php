<?php

declare(strict_types=1);

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
    protected Client $httpClient;

    /** @var array<string, mixed> */
    protected array $config;

    public function __construct(?string $connection = null)
    {
        $this->config = Config::get('lara_client.connections.'.($connection ?: Config::get('lara_client.default')));
        $this->httpClient = new Client([
            'base_uri' => $this->config['base_uri'],
            'headers' => $this->config['default_headers'],
            'timeout' => $this->config['timeout'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $queryParams
     *
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function get(string $uri, array $queryParams = []): Response
    {
        $fullUrl = $this->getFullUrl($uri);

        return $this->request('GET', $fullUrl, ['query' => $queryParams]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function post(string $uri, array $data = []): Response
    {
        $fullUrl = $this->getFullUrl($uri);

        return $this->request('POST', $fullUrl, ['json' => $data]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function put(string $uri, array $data = []): Response
    {
        $fullUrl = $this->getFullUrl($uri);

        return $this->request('PUT', $fullUrl, ['json' => $data]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function patch(string $uri, array $data = []): Response
    {
        $fullUrl = $this->getFullUrl($uri);

        return $this->request('PATCH', $fullUrl, ['json' => $data]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws GuzzleException
     * @throws LaraClientApiClientException
     */
    public function delete(string $uri, array $data = []): Response
    {
        $fullUrl = $this->getFullUrl($uri);

        return $this->request('DELETE', $fullUrl, ['json' => $data]);
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws LaraClientApiClientException
     * @throws GuzzleException
     */
    protected function request(string $method, string $uri, array $options): Response
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
            throw new LaraClientApiClientException(
                (string) $response->getReasonPhrase(),
                $response->getStatusCode(),
                $response,
            );
        }

        return new Response($response, $method, $uri);
    }

    /**
     * @param  array<string, mixed>  $additionalHeaders
     * @return array<string, mixed>
     */
    protected function getHeaders(array $additionalHeaders = []): array
    {
        // Merge the default headers with any additional headers passed in
        $headers = array_merge($this->config['default_headers'], $additionalHeaders);

        // Add the Authorization header if an API key is set
        if (! empty($this->config['api_key'])) {
            $headers['Authorization'] = 'Bearer '.$this->config['api_key'];
        }

        return $headers;
    }

    /**
     * @param  list<string>  $resetHeader
     */
    protected function handleRateLimit(array $resetHeader): void
    {
        if (! empty($resetHeader)) {
            $resetTimestamp = (int) $resetHeader[0];
            $currentTimestamp = time();

            if ($resetTimestamp > $currentTimestamp) {
                $waitTime = $resetTimestamp - $currentTimestamp;
                Cache::put('api_rate_limit', true, $waitTime);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function logRequest(string $method, string $uri, array $options, ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        $responseBody = (string) $response->getBody();

        LaraClientLog::create([
            'endpoint' => $uri,
            'method' => $method,
            'request_payload' => json_encode($options),
            'response_status' => $status,
            'response_body' => $responseBody,
            'created_at' => now(),
        ]);
    }

    protected function getFullUrl(string $uri): string
    {
        $fullUrl = $uri;
        if (! preg_match('/^https?:\/\//', $uri)) {
            $fullUrl = $this->config['base_uri'].$uri;
        }

        return $fullUrl;
    }
}
