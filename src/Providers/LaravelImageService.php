<?php namespace GeneaLabs\LaravelImage\Providers;

use Illuminate\Support\AggregateServiceProvider;

class LaravelImageService extends AggregateServiceProvider
{
    protected $defer = false;
    protected $providers = [
        // Intervention image, etc.
    ];

    public function boot()
    {
        //
    }

    public function register()
    {
        parent::register();
    }

    public function provides() : array
    {
        return ['genealabs-laravel-image'];
    }
}
