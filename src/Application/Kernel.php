<?php

namespace Fjord\Application;

use Illuminate\View\View;

class Kernel
{
    /**
     * Fjord application instance.
     * 
     * @var Fjord\Application\Application
     */
    protected $app;

    /**
     * List of bootstrappers that should be executed before when the 
     * kernel is initialized. They get executed in the given order.
     * 
     * @var array
     */
    protected $bootstrappers = [
        Bootstrap\RegisterSingletons::class,
        Bootstrap\BootstrapTranslator::class,
        Bootstrap\BootstrapKernel::class,
        Bootstrap\DiscoverPackages::class,
        Bootstrap\RegisterConfigFactories::class,
        Bootstrap\BootstrapVueApplication::class,
        Bootstrap\RegisterPackages::class,
    ];

    /**
     * The Fjord extension provided by your application.
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * Formatted array of extensions to allow multiple extensions 
     * for one component.
     *
     * @var array
     */
    protected $formattedExtensions = [];

    /**
     * Fjord application service providers.
     *
     * @var array
     */
    public $providers = [];

    /**
     * Create a new Fjord kernel instance.
     *
     * @param  \Fjord\Application\Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->bootstrap();
    }

    /**
     * Handle incomming route.
     * 
     * @return void
     */
    public function handleRoute($route)
    {
        // TODO: Find something to do for here.
    }

    /**
     * Handle fjord::app view before it gets executed.
     * 
     * @return void
     */
    public function handleView(View $view)
    {
        $this->build($view);

        //$this->extend($view);
    }

    /**
     * Get the bootstrap classes for the application.
     * 
     * @return void
     */
    public function bootstrap()
    {
        $this->registerRootExtensions();

        $this->app->bootstrapWith($this->bootstrappers, $this);
    }

    /**
     * Register all extensions that are defined in the $extensions variable.
     * 
     * @return void
     */
    protected function registerRootExtensions()
    {
        foreach ($this->extensions as $component => $extension) {
            $this->registerExtension($component, $extension);
        }
    }

    /**
     * Execute extensions for the given components.
     * 
     * @param Illuminate\View\View $view
     * @return void
     */
    public function extend(View $view)
    {
        //
    }

    /**
     * Build application for the given route.
     * 
     * @param Illuminate\View\View $view
     * @return void
     */
    public function build(View $view)
    {
        $this->app->build($view);
    }

    /**
     * Register extension class.
     * 
     * @param string $component
     * @param string $extension
     * @return void
     */
    public function registerExtension(string $key, string $extension)
    {
        $this->app->registerExtension($key, $extension);
    }
}
