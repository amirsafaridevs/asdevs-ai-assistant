<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Contracts\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        add_action('admin_footer', [$this, 'renderWidgetContainer']);
        add_action('admin_head', [$this, 'addMetaViewport']);
    }

    /**
     * Render the Vue app mount point in admin footer.
     */
    public function renderWidgetContainer(): void
    {
        echo '<div id="asdevs-ai-assistant-app"></div>';
    }

    /**
     * Add meta viewport for proper scaling.
     */
    public function addMetaViewport(): void
    {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    }
}
