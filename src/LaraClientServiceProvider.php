<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Usamamuneerchaudhary\LaraClient\Console\CheckCommand;
use Usamamuneerchaudhary\LaraClient\Console\MakeClientCommand;
use Usamamuneerchaudhary\LaraClient\Console\PruneCommand;

class LaraClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lara_client.php', 'lara_client');

        $this->app->singleton(LaraClientManager::class, fn ($app) => new LaraClientManager(
            $app->make(ConfigRepository::class),
            $app->make(Dispatcher::class),
            $app->make(CacheFactory::class),
        ));

        $this->app->alias(LaraClientManager::class, 'laraclient');
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerGate();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laraclient');

        $this->registerRoutes();
        $this->registerPulse();
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/lara_client.php' => config_path('lara_client.php'),
        ], 'laraclient-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/laraclient'),
        ], 'laraclient-views');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'laraclient-migrations');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckCommand::class,
                PruneCommand::class,
                MakeClientCommand::class,
            ]);
        }
    }

    /**
     * The dashboard exposes request and response bodies. Defining a permissive
     * default would be the wrong call, so the gate denies by default outside
     * local and the application defines who may look.
     */
    protected function registerGate(): void
    {
        if (Gate::has('viewLaraClient')) {
            return;
        }

        Gate::define('viewLaraClient', fn ($user = null): bool => $this->app->environment('local'));
    }

    protected function registerRoutes(): void
    {
        if (! config('lara_client.dashboard.enabled', true)) {
            return;
        }

        Route::group([
            'prefix' => config('lara_client.dashboard.path', 'laraclient'),
            'middleware' => config('lara_client.dashboard.middleware', ['web']),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    protected function registerPulse(): void
    {
        if (! config('lara_client.telemetry.pulse', true)) {
            return;
        }

        if (! class_exists(\Laravel\Pulse\Facades\Pulse::class)) {
            return;
        }

        $this->app->make(Dispatcher::class)->listen(
            Events\ResponseReceived::class,
            [Pulse\PulseRecorder::class, 'handleResponse'],
        );

        $this->app->make(Dispatcher::class)->listen(
            Events\RequestFailed::class,
            [Pulse\PulseRecorder::class, 'handleFailure'],
        );
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [LaraClientManager::class, 'laraclient'];
    }
}
