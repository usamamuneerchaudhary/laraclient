<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Exceptions;

class FixtureNotFoundException extends LaraClientException
{
    public static function for(string $method, string $uri, string $path): self
    {
        return new self(
            "No recorded fixture for [{$method} {$uri}] in [{$path}]. ".
            'Re-run with LARACLIENT_RECORDER=record to capture it.'
        );
    }
}
