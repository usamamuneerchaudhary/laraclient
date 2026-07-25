<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Support;

use Illuminate\Support\Arr;
use Usamamuneerchaudhary\LaraClient\Exceptions\ConnectionNotConfiguredException;

/**
 * Merges a named connection over the global defaults so each integration only
 * declares what makes it different.
 */
class ConnectionConfig
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public readonly string $name,
        protected array $config,
    ) {}

    /**
     * @param  array<string, mixed>  $root
     */
    public static function resolve(string $name, array $root): self
    {
        $connections = $root['connections'] ?? [];

        if (! array_key_exists($name, $connections)) {
            throw ConnectionNotConfiguredException::for($name, array_keys($connections));
        }

        $merged = static::mergeDeep(
            $root['defaults'] ?? [],
            $connections[$name] ?? [],
        );

        $merged['redact'] = $root['redact'] ?? [];
        $merged['recorder'] = $root['recorder'] ?? [];
        $merged['telemetry'] = $root['telemetry'] ?? [];

        return new self($name, $merged);
    }

    /**
     * Recursive merge where associative arrays merge and lists replace.
     * A connection overriding retry. statuses should get exactly its own list,
     * not its list appended to the defaults.
     */
    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    protected static function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && ! array_is_list($value)
            ) {
                $base[$key] = static::mergeDeep($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /** @return array<string, mixed> */
    public function section(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? $value : [];
    }

    public function enabled(string $feature): bool
    {
        return (bool) $this->get("{$feature}.enabled", false);
    }

    public function baseUri(): string
    {
        return rtrim((string) $this->get('base_uri', ''), '/').'/';
    }

    public function healthPath(): ?string
    {
        $path = $this->get('health_path');

        if ($path === null || $path === '') {
            return null;
        }

        return (string) $path;
    }

    /** @return array<string, mixed> */
    public function healthQuery(): array
    {
        $query = $this->get('health_query', []);

        return is_array($query) ? $query : [];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return new self($this->name, static::mergeDeep($this->config, $overrides));
    }
}
