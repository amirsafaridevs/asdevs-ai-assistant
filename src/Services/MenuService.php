<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Services;

class MenuService
{
    /**
     * Get normalized admin menu tree.
     *
     * During REST API requests, the global $menu array may not be populated
     * because admin_menu action hasn't fired. We lazily initialize it here.
     */
    public function getMenus(): array
    {
        global $menu, $submenu;

        // Ensure the admin menu is built — during REST API requests it may not be.
        // This is safe to call multiple times because add_action hooks are
        // only fired once and the globals persist for the request lifetime.
        if (!is_array($menu) || empty($menu)) {
            $this->initializeAdminMenu();
        }

        if (!is_array($menu)) {
            return [];
        }

        $items = [];

        foreach ($menu as $item) {
            if (!is_array($item) || empty($item[0]) || empty($item[2])) {
                continue;
            }

            $menuTitle = $this->stripTags($item[0]);
            $menuSlug  = $item[2];
            $menuUrl   = $this->buildUrl($menuSlug);

            // Recursively build submenu tree — supports deeply nested submenus
            // that some plugins add (e.g., submenu → submenu → submenu)
            $subItems = $this->buildSubmenuTree($menuSlug);

            $items[] = [
                'title'    => $menuTitle,
                'slug'     => $menuSlug,
                'url'      => $menuUrl,
                'children' => $subItems,
            ];
        }

        return $items;
    }

    /**
     * Recursively build a submenu tree for the given parent slug.
     *
     * WordPress $submenu is a flat array keyed by parent slug. This method
     * walks the $submenu array recursively so that deeply nested submenus
     * (e.g., WooCommerce → Settings → Advanced → Features) are fully
     * represented in the output.
     */
    private function buildSubmenuTree(string $parentSlug): array
    {
        global $submenu;

        $items = [];

        if (!isset($submenu[$parentSlug]) || !is_array($submenu[$parentSlug])) {
            return $items;
        }

        foreach ($submenu[$parentSlug] as $sub) {
            if (!is_array($sub) || empty($sub[0]) || empty($sub[2])) {
                continue;
            }

            $subTitle = $this->stripTags($sub[0]);
            $subSlug  = $sub[2];
            $subUrl   = $this->buildUrl($subSlug);

            // Recurse: check if this submenu item has its own children
            $children = $this->buildSubmenuTree($subSlug);

            $items[] = [
                'title'    => $subTitle,
                'slug'     => $subSlug,
                'url'      => $subUrl,
                'children' => $children,
            ];
        }

        return $items;
    }

    /**
     * Lazily initialize the WordPress admin menu structure.
     *
     * During REST API requests, $menu and $submenu globals are not populated
     * because wp-admin/menu.php is never loaded and admin_menu action never fires.
     * This method loads the core menu file and triggers the action so that
     * getMenus() can read the FULL menu tree including all submenus.
     */
    private function initializeAdminMenu(): void
    {
        global $menu, $submenu;

        // Ensure globals are initialized as arrays (WordPress sets them in wp-settings.php,
        // but during REST requests they may be null instead of empty arrays)
        if (!is_array($menu)) {
            $menu = [];
        }
        if (!is_array($submenu)) {
            $submenu = [];
        }

        // Ensure admin API functions are available
        if (!function_exists('add_menu_page')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Load the WordPress core menu file to populate $menu and $submenu
        // with the default admin sidebar structure (Dashboard, Posts, Media,
        // Pages, Comments, Appearance, Plugins, Users, Tools, Settings).
        // This file is normally only loaded during admin page renders, NOT
        // during REST requests, which is why we must include it explicitly.
        //
        // Output buffering prevents any accidental whitespace/notices from
        // leaking into the REST JSON response.
        $menuFile = ABSPATH . 'wp-admin/menu.php';
        if (file_exists($menuFile)) {
            ob_start();
            require $menuFile;
            ob_end_clean();
        }

        // Fire admin_menu action so all plugins/themes register their menu pages.
        // This adds plugin-specific menus and submenus on top of the core structure.
        // Safe to call during REST requests — hooks fire only once per request.
        do_action('admin_menu');
    }

    /**
     * Build a relative admin path (slug) from a menu slug.
     * Returns ONLY the relative path (e.g., "admin.php?page=wc-settings&tab=advanced")
     * NEVER a full URL — the frontend sends this slug back to the backend,
     * and the backend constructs the full URL using admin_url() to avoid double wp-admin.
     */
    private function buildUrl(string $slug): string
    {
        // If it's a full URL, extract just the relative path
        if (str_starts_with($slug, 'http://') || str_starts_with($slug, 'https://')) {
            $parsed = wp_parse_url($slug);
            $path = $parsed['path'] ?? '';
            $query = $parsed['query'] ?? '';
            // Strip wp-admin/ prefix from path
            $path = preg_replace('#^/?(wp-admin/)?#', '', $path);
            $slug = $path;
            if ($query) {
                $slug .= '?' . $query;
            }
            return $slug;
        }

        // Strip any wp-admin/ prefix to avoid doubling
        $slug = preg_replace('#^wp-admin/#', '', $slug);

        // Return the relative slug as-is (frontend + backend will handle full URL construction)
        if (str_contains($slug, '.php')) {
            return $slug;
        }

        return 'admin.php?page=' . $slug;
    }

    /**
     * Strip HTML tags and decode entities.
     */
    private function stripTags(string $text): string
    {
        // Remove WordPress update count spans like <span class='update-plugins count-3'>...
        $text = preg_replace('/<span[^>]*>.*?<\/span>/i', '', $text);
        $text = wp_strip_all_tags($text, true);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($text);
    }
}
