<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Pulse;

use Laravel\Pulse\Facades\Pulse;
use Usamamuneerchaudhary\LaraClient\Events\RequestFailed;
use Usamamuneerchaudhary\LaraClient\Events\ResponseReceived;

/**
 * Feeds outbound API latency and failures into Pulse.
 *
 * Registered only when laravel/pulse is installed.
 */
class PulseRecorder
{
    public static function handleResponse(ResponseReceived $event): void
    {
        if ($event->fromCache) {
            return;
        }

        Pulse::record(
            'laraclient_duration',
            $event->connection,
            (int) round($event->durationMs),
        )->avg()->max()->count();

        if ($event->response->getStatusCode() >= 400) {
            Pulse::record('laraclient_error', $event->connection)->count();
        }
    }

    public static function handleFailure(RequestFailed $event): void
    {
        Pulse::record('laraclient_error', $event->connection)->count();
    }
}
