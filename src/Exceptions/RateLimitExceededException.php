<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Exceptions;

/**
 * Raised instead of blocking a worker with sleep(). Queued jobs can hand the
 * work straight back to the queue:
 *
 *     } catch (RateLimitExceededException $e) {
 *         $this->release($e->retryAfter);
 *     }
 */
class RateLimitExceededException extends LaraClientException
{
    public function __construct(
        public readonly int $retryAfter,
        ?string $connection = null,
        string $message = '',
    ) {
        parent::__construct(
            $message !== '' ? $message : sprintf(
                'Rate limit reached for connection [%s]. Retry in %d second(s).',
                $connection ?? 'default',
                $retryAfter,
            ),
            $connection,
            429,
        );
    }

    public function retryAfterAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@'.(time() + $this->retryAfter));
    }
}
