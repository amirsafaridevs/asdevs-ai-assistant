<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant;

use ASDevs\AIAssistant\Providers\AdminServiceProvider;
use ASDevs\AIAssistant\Providers\RestApiServiceProvider;
use ASDevs\AIAssistant\Providers\AssetServiceProvider;
use ASDevs\AIAssistant\Providers\ContextServiceProvider;
use ASDevs\AIAssistant\Providers\SettingsServiceProvider;

class App
{
    private static ?App $instance = null;
    private Container $container;

    private function __construct()
    {
        $this->container = new Container();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Bootstrap the plugin.
     */
    public function boot(): void
    {
        // Register the container as a singleton
        $this->container->instance(Container::class, $this->container);

        // Register service providers
        $this->registerProviders();

        // Fire the boot sequence
        do_action('asdevs_ai_assistant_booted', $this);
    }

    /**
     * Register all service providers.
     */
    private function registerProviders(): void
    {
        $providers = [
            SettingsServiceProvider::class,
            ContextServiceProvider::class,
            RestApiServiceProvider::class,
            AssetServiceProvider::class,
            AdminServiceProvider::class,
        ];

        foreach ($providers as $providerClass) {
            /** @var \ASDevs\AIAssistant\Contracts\ServiceProvider $provider */
            $provider = new $providerClass($this->container);
            $provider->register();
        }
    }

    /**
     * Get the container instance.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}
