<?php

namespace Ordent\RamenAuth\Providers;

use Illuminate\Support\ServiceProvider;
use Tymon\JWTAuth\Providers\JWTAuthServiceProvider;
class RamenAuthProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        
        // $this->loadRoutesFrom(__DIR__.'/../Routes/routes.php');

        // $responseFactory = $this->app[\Ordent\RamenRest\Response\RestResponse::class];
        // foreach (get_class_methods($responseFactory) as $method){
        //     \Response::macro($method, [$responseFactory, $method]);
        // }
        // \App::bind(
        //     \Illuminate\Contracts\Debug\ExceptionHandler::class,
        //     \Ordent\RamenRest\Exception\Handler::class
        // );

        // $this->publishes([
        //     __DIR__.'/../config/ramen.php' => config_path('ramen.php'),
        // ]);

        
        
        // \Event::listen('Ordent\RamenRest\Events\FileHandlerEvent', 'Ordent\RamenRest\Listeners\FileHandlerListener@handle');
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
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('JWTAuth',\Tymon\JWTAuth\Facades\JWTAuth::class);
        // $this->mergeConfigFrom(
        //     __DIR__.'/../config/ramen.php', 'ramen'
        // );
    }
}
