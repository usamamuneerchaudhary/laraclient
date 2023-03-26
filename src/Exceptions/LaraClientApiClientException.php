<?php

namespace Usamamuneerchaudhary\LaraClient\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class LaraClientApiClientException extends Exception
{
    protected $statusCode;
    protected $response;

    /**
     * @param $message
     * @param $statusCode
     * @param $response
     */
    public function __construct($message = '', $statusCode = null, $response = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    /**
     * @return mixed|null
     */
    public function getStatusCode(): mixed
    {
        return $this->statusCode;
    }

    /**
     * @return mixed|null
     */
    public function getResponse(): mixed
    {
        return $this->response;
    }

    /**
     * @return void
     */
    public function report(): void
    {
        Log::debug('HTTP Error with Lara Client API');
    }

    /**
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request): \Illuminate\Http\JsonResponse
    {
        return response()->json(["error" => true, "message" => $this->getStatusCode()]);
    }
}
