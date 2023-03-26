<?php

namespace Usamamuneerchaudhary\LaraClient;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class LaraClientServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lara_client.php', 'lara_client');
    }

    /**
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Publish config file
            $this->publishes([
                __DIR__.'/../config/lara_client.php' => config_path('lara_client.php'),
            ], 'config');
            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/laraclient'),
            ], 'views');
        }
        $this->loadMigrationsFrom(__DIR__.'/../migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laraclient');
        $this->registerRoutes();
    }

    /**
     * @return void
     */
    protected function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * @return string[]
     */
    protected function routeConfiguration()
    {
        return [
            'prefix' => 'laraclient',
//            'middleware'=>''
        ];
    }
}
