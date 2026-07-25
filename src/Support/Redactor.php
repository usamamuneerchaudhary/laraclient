<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Support;

/**
 * Masks credentials before they reach the logs table or the dashboard.
 */
class Redactor
{
    /** @var list<string> */
    protected array $headerKeys;

    /** @var list<string> */
    protected array $bodyKeys;

    protected string $replacement = '[redacted]';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->headerKeys = array_map(
            strtolower(...),
            $config['headers'] ?? []
        );

        $this->bodyKeys = array_map(
            strtolower(...),
            $config['body'] ?? []
        );

        $this->replacement = $config['replacement'] ?? '[redacted]';
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public function headers(array $headers): array
    {
        $clean = [];

        foreach ($headers as $name => $value) {
            $clean[$name] = in_array(strtolower((string) $name), $this->headerKeys, true)
                ? $this->replacement
                : $value;
        }

        return $clean;
    }

    /**
     * Recursively masks sensitive keys in a decoded body.
     */
    public function body(mixed $body): mixed
    {
        if (is_array($body)) {
            $clean = [];

            foreach ($body as $key => $value) {
                $clean[$key] = is_string($key) && in_array(strtolower($key), $this->bodyKeys, true)
                    ? $this->replacement
                    : $this->body($value);
            }

            return $clean;
        }

        if ($body instanceof \stdClass) {
            return (object) $this->body((array) $body);
        }

        return $body;
    }

    /**
     * Redacts a raw payload string. JSON is decoded, masked and re-encoded;
     * form-encoded bodies are masked key by key. Anything else is passed
     * through untouched because we cannot see inside it safely.
     */
    public function payload(?string $raw, ?string $contentType = null): ?string
    {
        if ($raw === null || $raw === '') {
            return $raw;
        }

        if (str_contains((string) $contentType, 'x-www-form-urlencoded')) {
            parse_str($raw, $parsed);

            return http_build_query($this->body($parsed));
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $raw;
        }

        return json_encode(
            $this->body($decoded),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: $raw;
    }

    /**
     * Query strings can carry API keys too (?api_key=...).
     */
    public function url(string $url): string
    {
        $parts = parse_url($url);

        if (empty($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);

        $masked = [];
        foreach ($query as $key => $value) {
            $masked[$key] = in_array(strtolower((string) $key), $this->bodyKeys, true)
                || in_array(strtolower((string) $key), $this->headerKeys, true)
                ? $this->replacement
                : $value;
        }

        $rebuilt = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');

        return $rebuilt.'?'.http_build_query($masked);
    }

    public function truncate(?string $value, int $max): ?string
    {
        if ($value === null || $max <= 0 || strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max)."\n… truncated (".strlen($value).' bytes total)';
    }
}
