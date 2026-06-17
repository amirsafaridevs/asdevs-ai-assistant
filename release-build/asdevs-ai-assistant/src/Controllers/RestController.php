<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Controllers;

use ASDevs\AIAssistant\Services\ContextService;
use ASDevs\AIAssistant\Services\ThemeService;
use ASDevs\AIAssistant\Services\PluginService;
use ASDevs\AIAssistant\Services\MenuService;
use ASDevs\AIAssistant\Services\CurrentPageService;
use WP_REST_Request;
use WP_REST_Response;

class RestController
{
    private const NAMESPACE = 'asdevs-ai-assistant/v1';

    public function __construct(
        private ContextService $contextService,
        private ThemeService $themeService,
        private PluginService $pluginService,
        private MenuService $menuService,
        private CurrentPageService $currentPageService,
    ) {}

    /**
     * Register all REST routes.
     */
    public function registerRoutes(): void
    {
        // GET /context
        register_rest_route(self::NAMESPACE, '/context', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getContext'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // GET /theme
        register_rest_route(self::NAMESPACE, '/theme', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getTheme'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // GET /plugins
        register_rest_route(self::NAMESPACE, '/plugins', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPlugins'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // GET /menus
        register_rest_route(self::NAMESPACE, '/menus', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getMenus'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // GET /current-page
        register_rest_route(self::NAMESPACE, '/current-page', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getCurrentPage'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // POST /navigate
        register_rest_route(self::NAMESPACE, '/navigate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'navigate'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'url' => [
                    'required'          => false,
                    'type'              => 'string',
                    'description'       => 'The full admin URL to navigate to (deprecated, use slug)',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'slug' => [
                    'required'          => false,
                    'type'              => 'string',
                    'description'       => 'The relative admin page slug (e.g., admin.php?page=wc-settings&tab=advanced)',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Permission check: only administrators may access admin context endpoints.
     */
    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * GET /context - full aggregated context.
     */
    public function getContext(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->contextService->getContextSummary(), 200);
    }

    /**
     * GET /theme
     */
    public function getTheme(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->themeService->getTheme(), 200);
    }

    /**
     * GET /plugins
     */
    public function getPlugins(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->pluginService->getPlugins(), 200);
    }

    /**
     * GET /menus
     */
    public function getMenus(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->menuService->getMenus(), 200);
    }

    /**
     * GET /current-page
     */
    public function getCurrentPage(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->currentPageService->getCurrentPage(), 200);
    }

    /**
     * POST /navigate - validate navigation target and return the full admin URL.
     * Accepts either a relative slug or a full URL. The backend constructs the
     * final URL using admin_url() to avoid double wp-admin issues.
     * Backend never redirects. Frontend performs the redirect.
     */
    public function navigate(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $url  = $request->get_param('url');

        // Prefer slug over full URL
        if (!empty($slug)) {
            $url = $this->buildAdminUrl($slug);
        } elseif (!empty($url)) {
            // Backward compatibility: if a full URL is sent, normalize it
            $url = $this->normalizeUrl($url);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Either slug or url is required.',
            ], 400);
        }

        // Validate: must be an admin URL
        $adminUrl = admin_url();
        if (!str_starts_with($url, $adminUrl)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Only admin URLs are allowed.',
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'url'     => $url,
        ], 200);
    }

    /**
     * Build a full admin URL from a relative slug.
     * The slug should be something like "admin.php?page=wc-settings&tab=advanced"
     * or "edit.php?post_type=page".
     */
    private function buildAdminUrl(string $slug): string
    {
        // Strip any leading/trailing slashes and wp-admin/ prefix
        $slug = ltrim($slug, '/');
        $slug = preg_replace('#^wp-admin/#', '', $slug);

        // If it's already a full URL, extract the relative part
        if (str_starts_with($slug, 'http://') || str_starts_with($slug, 'https://')) {
            $parsed = wp_parse_url($slug);
            $path  = ltrim($parsed['path'] ?? '', '/');
            $path  = preg_replace('#^wp-admin/#', '', $path);
            $query = $parsed['query'] ?? '';
            $slug  = $path;
            if ($query) {
                $slug .= '?' . $query;
            }
        }

        return admin_url($slug);
    }

    /**
     * Normalize a full URL: extract the relative path and rebuild using admin_url()
     * to guarantee no double wp-admin.
     */
    private function normalizeUrl(string $url): string
    {
        $parsed = wp_parse_url($url);
        $path  = ltrim($parsed['path'] ?? '', '/');
        $path  = preg_replace('#^wp-admin/#', '', $path);
        $query = $parsed['query'] ?? '';
        $slug  = $path;
        if ($query) {
            $slug .= '?' . $query;
        }
        return admin_url($slug);
    }
}
