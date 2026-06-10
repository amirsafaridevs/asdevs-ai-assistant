<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Contracts\ServiceProvider;
use ASDevs\AIAssistant\Services\SettingsService;
use ASDevs\AIAssistant\Admin\SettingsPage;
use ASDevs\AIAssistant\Controllers\SettingsController;
use ASDevs\AIAssistant\Controllers\ChatProxyController;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the settings service
        $this->container->singleton(SettingsService::class, SettingsService::class);

        // Register the settings admin page
        $this->container->singleton(SettingsPage::class, SettingsPage::class);

        // Register the settings REST controller
        $this->container->singleton(SettingsController::class, SettingsController::class);

        // Register the chat proxy controller
        $this->container->singleton(ChatProxyController::class, ChatProxyController::class);

        // Boot the settings page (adds menu)
        add_action('init', function () {
            /** @var SettingsPage $page */
            $page = $this->container->make(SettingsPage::class);
            $page->register();
        });

        // Register settings + chat proxy REST routes
        add_action('rest_api_init', function () {
            /** @var SettingsController $controller */
            $controller = $this->container->make(SettingsController::class);
            $controller->registerRoutes();

            /** @var ChatProxyController $proxyController */
            $proxyController = $this->container->make(ChatProxyController::class);
            $proxyController->registerRoutes();
        });
    }
}
