<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Contracts\ServiceProvider;
use ASDevs\AIAssistant\Controllers\RestController;

class RestApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(RestController::class, RestController::class);

        add_action('rest_api_init', function () {
            /** @var RestController $controller */
            $controller = $this->container->make(RestController::class);
            $controller->registerRoutes();
        });
    }
}
