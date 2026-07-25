<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<LaraClientLog>
 */
class LaraClientLogBuilder extends Builder
{
    public function forConnection(?string $connection): self
    {
        return $this->when($connection, fn (self $query) => $query->where('connection', $connection));
    }

    public function forEndpoint(?string $endpoint): self
    {
        return $this->when(
            $endpoint,
            fn (self $query) => $query->where('endpoint', 'like', '%'.$endpoint.'%'),
        );
    }

    public function failed(): self
    {
        return $this->where(fn (self $query) => $query->where('status', '>=', 400)->orWhereNull('status'));
    }

    public function slowerThan(int $milliseconds): self
    {
        return $this->where('duration_ms', '>=', $milliseconds);
    }
}
