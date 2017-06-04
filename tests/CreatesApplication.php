<?php namespace GeneaLabs\LaravelImage\Tests;

use GeneaLabs\LaravelImage\Providers\LaravelImageService;
use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication()
    {
        $app = require(__DIR__ . '/../vendor/laravel/laravel/bootstrap/app.php');
        $app->make(Kernel::class)->bootstrap();
        $app->register(LaravelImageService::class);

        return $app;
    }
}
