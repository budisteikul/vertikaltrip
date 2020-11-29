<?php

namespace budisteikul\tourfront;

use Illuminate\Support\ServiceProvider;

class TourFrontServiceProvider extends ServiceProvider
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
        $this->loadViewsFrom(__DIR__.'/views', 'tourfront');
        
        $this->publishes([ __DIR__.'/publish/manifest' => public_path(''),], 'public');
        $this->publishes([ __DIR__.'/publish/assets' => public_path('assets'),], 'public');
        $this->publishes([ __DIR__.'/publish/css' => public_path('css'),], 'public');
        $this->publishes([ __DIR__.'/publish/js' => public_path('js'),], 'public');
        $this->publishes([ __DIR__.'/publish/img' => public_path('img'),], 'public');
        $this->publishes([ __DIR__.'/publish/foodtour' => public_path('assets/foodtour'),], 'public');

        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }
}
