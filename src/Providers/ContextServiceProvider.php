<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Contracts\ServiceProvider;
use ASDevs\AIAssistant\Services\ThemeService;
use ASDevs\AIAssistant\Services\PluginService;
use ASDevs\AIAssistant\Services\MenuService;
use ASDevs\AIAssistant\Services\CurrentPageService;
use ASDevs\AIAssistant\Services\ContextService;

class ContextServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ThemeService::class, ThemeService::class);
        $this->container->singleton(PluginService::class, PluginService::class);
        $this->container->singleton(MenuService::class, MenuService::class);
        $this->container->singleton(CurrentPageService::class, CurrentPageService::class);
        $this->container->singleton(ContextService::class, ContextService::class);
    }
}
