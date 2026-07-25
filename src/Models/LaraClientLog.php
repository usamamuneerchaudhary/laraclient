<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * @property int $id
 * @property string $connection
 * @property string $method
 * @property string $endpoint
 * @property int|null $status
 * @property int|null $duration_ms
 * @property int $attempt
 * @property bool $cached
 *
 * @method static LaraClientLogBuilder query()
 * @method static LaraClientLog create(array<string, mixed> $attributes = [])
 */
class LaraClientLog extends Model
{
    protected $table = 'laraclient_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'duration_ms' => 'integer',
            'attempt' => 'integer',
            'cached' => 'boolean',
            'request_headers' => 'array',
            'response_headers' => 'array',
        ];
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder($query): LaraClientLogBuilder
    {
        return new LaraClientLogBuilder($query);
    }

    public function getConnectionName(): ?string
    {
        return config('lara_client.database_connection') ?? parent::getConnectionName();
    }

    // ---------------- Presentation -----------------------------------------------------

    public function statusClass(): string
    {
        return match (true) {
            $this->status === null => 'error',
            $this->status >= 500 => 'error',
            $this->status >= 400 => 'warn',
            default => 'ok',
        };
    }

    public function shortEndpoint(): string
    {
        $parsed = parse_url($this->endpoint);

        return ($parsed['path'] ?? $this->endpoint)
            .(isset($parsed['query']) ? '?'.$parsed['query'] : '');
    }

    public function host(): string
    {
        return parse_url($this->endpoint, PHP_URL_HOST) ?: '';
    }
}
