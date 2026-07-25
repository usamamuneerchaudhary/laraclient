<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Http\Message\RequestInterface;
use Usamamuneerchaudhary\LaraClient\Contracts\AuthStrategy;
use Usamamuneerchaudhary\LaraClient\Exceptions\LaraClientException;

/**
 * OAuth2 client credentials grant with transparent token caching and refresh.
 *
 * The token is fetched on first use, cached until shortly before it expires,
 * and re-fetched on a 401. Nothing about this needs to appear in app code.
 */
class OAuth2ClientCredentials implements AuthStrategy
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        protected CacheRepository $cache,
        protected string $connection,
        protected string $tokenUrl,
        protected ?string $clientId,
        protected ?string $clientSecret,
        protected array $scopes = [],
        protected int $leeway = 60,
        protected ?Client $tokenClient = null,
        protected string $authStyle = 'body', // body | basic
    ) {}

    public function apply(RequestInterface $request): RequestInterface
    {
        $token = $this->token();

        if ($token === null) {
            return $request;
        }

        return $request->withHeader('Authorization', 'Bearer '.$token);
    }

    public function refresh(): bool
    {
        $this->cache->forget($this->cacheKey());

        return $this->token() !== null;
    }

    protected function token(): ?string
    {
        if (blank($this->clientId) || blank($this->clientSecret)) {
            return null;
        }

        $cached = $this->cache->get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->requestToken();
    }

    protected function requestToken(): ?string
    {
        $client = $this->tokenClient ?? new Client(['timeout' => 10]);

        $form = ['grant_type' => 'client_credentials'];

        if ($this->scopes !== []) {
            $form['scope'] = implode(' ', $this->scopes);
        }

        $options = [];

        if ($this->authStyle === 'basic') {
            $options['headers']['Authorization'] = 'Basic '.base64_encode(
                $this->clientId.':'.$this->clientSecret
            );
        } else {
            $form['client_id'] = $this->clientId;
            $form['client_secret'] = $this->clientSecret;
        }

        $options['form_params'] = $form;

        try {
            $response = $client->post($this->tokenUrl, $options);
        } catch (GuzzleException $e) {
            throw new LaraClientException(
                "Could not obtain an OAuth2 token for connection [{$this->connection}]: ".$e->getMessage(),
                $this->connection,
                0,
                $e,
            );
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (! is_array($payload) || ! isset($payload['access_token'])) {
            throw new LaraClientException(
                "The OAuth2 token endpoint for connection [{$this->connection}] did not return an access_token.",
                $this->connection,
            );
        }

        $token = (string) $payload['access_token'];
        $ttl = max(60, (int) ($payload['expires_in'] ?? 3600) - $this->leeway);

        $this->cache->put($this->cacheKey(), $token, $ttl);

        return $token;
    }

    protected function cacheKey(): string
    {
        return 'laraclient:oauth2:'.$this->connection.':'.sha1($this->tokenUrl.'|'.($this->clientId ?? ''));
    }
}
