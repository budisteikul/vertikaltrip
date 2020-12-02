<?php

namespace budisteikul\vertikaltrip;

use Illuminate\Support\ServiceProvider;

class VertikalTripServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/views', 'vertikaltrip');
        
        $this->publishes([ __DIR__.'/publish/manifest' => public_path(''),], 'public');
        $this->publishes([ __DIR__.'/publish/css' => public_path('css'),], 'public');
        $this->publishes([ __DIR__.'/publish/js' => public_path('js'),], 'public');
        $this->publishes([ __DIR__.'/publish/img' => public_path('img'),], 'public');
        $this->publishes([ __DIR__.'/publish/foodtour' => public_path('assets/foodtour'),], 'public');

        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }
}
