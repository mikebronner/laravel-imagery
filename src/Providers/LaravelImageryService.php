<?php namespace GeneaLabs\LaravelImagery\Providers;

use GeneaLabs\LaravelImagery\Imagery;
use GeneaLabs\LaravelImagery\Console\Commands\Publish;
use Illuminate\Support\AggregateServiceProvider;
use Intervention\Image\ImageServiceProvider;

class LaravelImageryService extends AggregateServiceProvider
{
    protected $defer = false;
    protected $providers = [
        ImageServiceProvider::class,
    ];

    public function boot()
    {
        $this->registerBladeDirective('imagery');

        $configPath = __DIR__ . '/../../config/genealabs-laravel-imagery.php';
        $this->publishes([
            $configPath => config_path('genealabs-laravel-imagery.php')
        ], 'config');
        $this->mergeConfigFrom($configPath, 'genealabs-laravel-imagery');

        $this->publishes([
            __DIR__ . '/../../public/' => public_path('genealabs-laravel-imagery'),
        ], 'assets');
    }

    public function register()
    {
        parent::register();

        $this->commands(Publish::class);
        $this->app->singleton('imagery', function () {
            return new Imagery();
        });
    }

    public function provides() : array
    {
        return ['genealabs-laravel-imagery'];
    }

    protected function registerBladeDirective($directive)
    {
        if (array_key_exists($directive, app('blade.compiler')->getCustomDirectives())) {
            throw new Exception("Blade directive '{$directive}' is already registered.");
        }

        app('blade.compiler')->directive($directive, function ($parameters) {
            $parameters = trim($parameters, "()");

            return "<?php echo app('imagery')->conjure({$parameters})->picture; ?>";
        });
    }
}
