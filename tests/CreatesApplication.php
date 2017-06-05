<?php namespace GeneaLabs\LaravelImagery\Tests;

use GeneaLabs\LaravelImagery\Providers\LaravelImageService;
use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication()
    {
        $app = require(__DIR__ . '/../vendor/laravel/laravel/bootstrap/app.php');
        $app->make(Kernel::class)->bootstrap();
        $app->register(LaravelImageService::class);
        $this->addTestImage();

        return $app;
    }

    protected function addTestImage()
    {
        copy(__DIR__ . '/assets/test.jpg', public_path('test.jpg'));
    }
}
