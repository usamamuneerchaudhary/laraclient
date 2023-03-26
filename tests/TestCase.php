<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * @param $app
     * @return string[]
     */
    protected function getPackageProviders($app)
    {
        return [\Usamamuneerchaudhary\LaraClient\LaraClientServiceProvider::class];
    }

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--realpath' => realpath(__DIR__.'/../migrations')
        ]);
    }


    /**
     * @param $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => ''
        ]);
        $app['config']->set('lara_client.default', 'geodb');
        $app['config']->set('lara_client.connections.geodb.base_uri',
            'https://wft-geo-db.p.rapidapi.com/v1/geo/');
        $app['config']->set('lara_client.connections.geodb.default_headers', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-RapidAPI-Key' => '23c6b0817bmsh5720c5bcb04bb86p151c03jsn8f683b20fbe2',
            'X-RapidAPI-Host' => 'wft-geo-db.p.rapidapi.com'
        ]);
        $app['config']->set('lara_client.connections.geodb.timeout', 30);

        $app['config']->set('lara_client.connections.weatherapi.base_uri',
            'https://weatherapi-com.p.rapidapi.com/');
        $app['config']->set('lara_client.connections.weatherapi.default_headers', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-RapidAPI-Key' => '23c6b0817bmsh5720c5bcb04bb86p151c03jsn8f683b20fbe2'
        ]);
        $app['config']->set('lara_client.connections.weatherapi.timeout', 30);
    }

}
