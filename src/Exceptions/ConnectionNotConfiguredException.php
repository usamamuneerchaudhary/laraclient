<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Exceptions;

class ConnectionNotConfiguredException extends LaraClientException
{
    /**
     * @param  list<string>  $available
     */
    public static function for(string $name, array $available = []): self
    {
        $hint = $available === []
            ? ' No connections are configured in config/lara_client.php.'
            : ' Configured connections: '.implode(', ', $available).'.';

        return new self("LaraClient connection [{$name}] is not configured.".$hint, $name);
    }
}
