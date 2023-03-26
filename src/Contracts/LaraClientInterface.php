<?php

namespace Usamamuneerchaudhary\LaraClient\Contracts;

interface LaraClientInterface
{
    /**
     * @param $uri
     * @param $queryParams
     * @return mixed
     */
    public function get($uri, $queryParams = []);

    /**
     * @param $uri
     * @param $data
     * @return mixed
     */
    public function post($uri, $data = []);

    /**
     * @param $uri
     * @param $data
     * @return mixed
     */
    public function put($uri, $data = []);

    /**
     * @param $uri
     * @param $data
     * @return mixed
     */
    public function patch($uri, $data = []);

    /**
     * @param $uri
     * @param $data
     * @return mixed
     */
    public function delete($uri, $data = []);
}
