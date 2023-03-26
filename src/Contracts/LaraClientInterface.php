<?php

namespace Usamamuneerchaudhary\LaraClient\Contracts;

interface LaraClientInterface
{
    /**
     * @param $uri
     * @param $queryParams
     * @return mixed
     */
    public function get($uri, $queryParams = []): mixed;

    /**
     * @param $uri
     * @param $data
     * @return mixed
     */
    public function post($uri, $data = []): mixed;

    /**
     * @param $uri
     * @param $data
     * @return mixed
     */
    public function put($uri, $data = []): mixed;

    /**
     * @param $uri
     * @param $data
     * @return mixed
     */
    public function patch($uri, $data = []): mixed;

    /**
     * @param $uri
     * @param $data
     * @return mixed
     */
    public function delete($uri, $data = []): mixed;
}
