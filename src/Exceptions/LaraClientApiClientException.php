<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LaraClientApiClientException extends Exception
{
    protected mixed $statusCode;

    protected mixed $response;

    public function __construct(string $message = '', mixed $statusCode = null, mixed $response = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public function getStatusCode(): mixed
    {
        return $this->statusCode;
    }

    public function getResponse(): mixed
    {
        return $this->response;
    }

    public function report(): void
    {
        Log::debug('HTTP Error with Lara Client API');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['error' => true, 'message' => $this->getStatusCode()]);
    }
}
