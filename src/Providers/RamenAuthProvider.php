<?php

namespace Ordent\RamenAuth\Providers;

use Illuminate\Support\ServiceProvider;
use Tymon\JWTAuth\Providers\JWTAuthServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Nexmo\Laravel\NexmoServiceProvider;
class RamenAuthProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $path = __DIR__."/views";
        $this->loadViewsFrom($path, 'ramenauth');
        // $this->loadViewsFrom($path, 'ramenauth');
        $this->publishes([
            $path => resource_path('views/vendor/ramenauth'),
        ]);
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
        $this->publishes([
            __DIR__.'/ramenauth.php' => config_path('ramenauth.php'),
        ]);
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->publishes([
            __DIR__.'/Assets' => public_path('vendor/ramenauth'),
        ], 'public');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(\Ordent\RamenRest\Providers\RamenRestProvider::class);
        $this->app->register(JWTAuthServiceProvider::class);
        $this->app->register(NexmoServiceProvider::class);
        $this->app->register(PermissionServiceProvider::class);
        $this->app->singleton('AuthManager', function($app){
            return app('\Ordent\RamenAuth\Manager\AuthManager');
        });
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('JWTAuth',\Tymon\JWTAuth\Facades\JWTAuth::class);
        $this->mergeConfigFrom(
            __DIR__.'/ramenauth.php', 'ramenauth'
        );
        // $this->mergeConfigFrom(
        //     __DIR__.'/../config/ramen.php', 'ramen'
        // );
    }
}
