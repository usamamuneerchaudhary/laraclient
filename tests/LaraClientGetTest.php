<?php

use Usamamuneerchaudhary\LaraClient\LaraClient;

class LaraClientGetTest extends TestCase
{
    /** @test */
    public function it_can_make_get_requests()
    {
        $client = new LaraClient('geodb');
        $response = $client->get('countries');
        $data = $response->getData();

        $this->assertIsObject($data);
        $arr = (array) $data;
        $this->assertArrayHasKey('metadata', $arr);
        $this->assertArrayHasKey('links', $arr);
        $this->assertArrayHasKey('data', $arr);
    }

    /** @test */
    public function it_throws_exception_for_failed_requests()
    {
        $client = new LaraClient('weatherapi');
        $this->expectException(\Usamamuneerchaudhary\LaraClient\Exceptions\LaraClientApiClientException::class);
        $client->get('/wrong.json', ['q' => 'london']);
    }

    /** @test */
    public function get_can_have_extra_params()
    {
        $client = new LaraClient('weatherapi');
        $response = $client->get('current.json', ['q' => 'london']);
        $data = $response->getData();
        $this->assertIsObject($data);
        $arr = (array) $data;
        $this->assertArrayHasKey('location', $arr);
        $this->assertArrayHasKey('current', $arr);
    }
}
