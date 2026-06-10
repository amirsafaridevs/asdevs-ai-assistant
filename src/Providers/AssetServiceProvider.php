<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Contracts\ServiceProvider;
use ASDevs\AIAssistant\Services\SettingsService;

class AssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Enqueue plugin assets in the admin area.
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        $distUrl = ASDEVS_AI_ASSISTANT_DIST_URL;

        // Enqueue Vue app JS
        wp_enqueue_script(
            'asdevs-ai-assistant',
            $distUrl . 'js/main.js',
            [],
            ASDEVS_AI_ASSISTANT_VERSION,
            true
        );

        // Enqueue Vue app CSS
        wp_enqueue_style(
            'asdevs-ai-assistant',
            $distUrl . 'css/main.css',
            [],
            ASDEVS_AI_ASSISTANT_VERSION
        );

        // Enqueue the plugin stylesheet that styles the floating widget
        wp_enqueue_style(
            'asdevs-ai-assistant-widget',
            ASDEVS_AI_ASSISTANT_ASSETS_URL . 'css/widget.css',
            [],
            ASDEVS_AI_ASSISTANT_VERSION
        );

        // Get settings service
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->make(SettingsService::class);

        // Pass WordPress data + AI settings to the frontend
        wp_localize_script('asdevs-ai-assistant', 'asdevsAiAssistant', [
            'apiUrl'      => rest_url('asdevs-ai-assistant/v1'),
            'nonce'       => wp_create_nonce('wp_rest'),
            'adminUrl'    => admin_url(),
            'siteName'    => get_bloginfo('name'),
            'siteUrl'     => site_url(),
            'currentPage' => self_admin_url(),
            'isAdmin'     => is_admin(),
            'userId'      => get_current_user_id(),
            // AI configuration (API key IS exposed — frontend calls AI directly per architecture spec)
            'aiProvider'   => $settingsService->getProvider(),
            'aiModel'      => $settingsService->getModel(),
            'aiEndpoint'   => $settingsService->getEndpoint(),
            'apiKey'       => $settingsService->getApiKey(),
            'isConfigured' => $settingsService->isConfigured(),
        ]);
    }
}
