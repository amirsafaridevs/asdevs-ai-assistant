<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Controllers;

use ASDevs\AIAssistant\Services\SettingsService;
use WP_REST_Request;
use WP_REST_Response;

class SettingsController
{
    private const NAMESPACE = 'asdevs-ai-assistant/v1';

    public function __construct(
        private SettingsService $settingsService,
    ) {}

    /**
     * Register the settings REST route.
     */
    public function registerRoutes(): void
    {
        // GET /settings - public settings (no API key)
        register_rest_route(self::NAMESPACE, '/settings', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSettings'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    /**
     * Permission check: admin only for settings.
     */
    public function checkPermission(): bool
    {
        return current_user_can('read');
    }

    /**
     * GET /settings - return public configuration.
     */
    public function getSettings(WP_REST_Request $request): WP_REST_Response
    {
        $public = $this->settingsService->getPublic();

        // Add is_configured flag
        $public['is_configured'] = $this->settingsService->isConfigured();

        return new WP_REST_Response($public, 200);
    }
}
