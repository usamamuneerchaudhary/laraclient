<?php

namespace Usamamuneerchaudhary\LaraClient;

class Response
{
    protected $statusCode;
    protected $data;

    /**
     * @param $statusCode
     * @param $data
     */
    public function __construct($statusCode, $data)
    {
        $this->statusCode = $statusCode;
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getStatusCode(): mixed
    {
        return $this->statusCode;
    }

    /**
     * @return mixed
     */
    public function getData(): mixed
    {
        return json_decode($this->data);
    }
}
