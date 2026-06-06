<?php

namespace Arhx\Improveme;

use Arhx\Improveme\Http\Middleware\InjectImproveme;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class ImprovemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/improveme.php', 'improveme');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'improveme');

        if (config('improveme.register_routes', true)) {
            $this->registerRoutes();
        }

        $this->registerBlade();
        $this->registerAutoInjection();
        $this->registerPublishing();
    }

    private function registerRoutes(): void
    {
        $this->app['router']
            ->middleware(config('improveme.middleware', ['web']))
            ->group(__DIR__.'/../routes/web.php');
    }

    private function registerBlade(): void
    {
        // @improveme  →  renders the widget snippet.
        Blade::directive('improveme', fn () => "<?php echo view('improveme::widget')->render(); ?>");
    }

    private function registerAutoInjection(): void
    {
        if (! config('improveme.enabled', true) || ! config('improveme.inject', true)) {
            return;
        }

        // Append to the web group so it runs after the response is built and a
        // session/csrf token is available for the snippet.
        $kernel = $this->app->make(Kernel::class);
        if (method_exists($kernel, 'appendMiddlewareToGroup')) {
            $kernel->appendMiddlewareToGroup('web', InjectImproveme::class);
        } else {
            $this->app['router']->pushMiddlewareToGroup('web', InjectImproveme::class);
        }
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/improveme.php' => config_path('improveme.php'),
        ], 'improveme-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/improveme'),
        ], 'improveme-views');

        $this->publishes([
            __DIR__.'/../resources/js/improveme.js' => public_path('vendor/improveme/improveme.js'),
        ], 'improveme-assets');
    }
}
