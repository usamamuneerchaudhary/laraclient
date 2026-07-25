<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Exceptions;

use Usamamuneerchaudhary\LaraClient\Response;

/**
 * Thrown for a 4xx/5xx response when ->throw() is used or throw_on_error is set.
 *
 * The response is always attached, so error bodies stay inspectable:
 *
 *     } catch (RequestException $e) {
 *         report($e->response->json('error.message'));
 *     }
 */
class RequestException extends LaraClientException
{
    public function __construct(
        public readonly Response $response,
        ?string $connection = null,
    ) {
        parent::__construct(
            sprintf(
                'HTTP request returned status %d for [%s %s]: %s',
                $response->status(),
                $response->method(),
                $response->url(),
                $response->summary(),
            ),
            $connection,
            $response->status(),
        );
    }
}
