<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Services;

class ContextService
{
    public function __construct(
        private ThemeService $themeService,
        private PluginService $pluginService,
        private MenuService $menuService,
        private CurrentPageService $currentPageService,
    ) {}

    /**
     * Aggregate all context for the AI.
     */
    public function getContext(): array
    {
        return [
            'site'        => $this->getSiteInfo(),
            'user'        => $this->getUserInfo(),
            'theme'       => $this->themeService->getTheme(),
            'plugins'     => $this->pluginService->getPlugins(),
            'menus'       => $this->menuService->getMenus(),
            'currentPage' => $this->currentPageService->getCurrentPage(),
        ];
    }

    /**
     * Get a summary context suitable for AI prompt.
     */
    public function getContextSummary(): array
    {
        $context  = $this->getContext();
        $activePlugins = array_filter($context['plugins'], fn($p) => $p['active']);
        $inactivePlugins = array_filter($context['plugins'], fn($p) => !$p['active']);

        return [
            'site'            => $context['site'],
            'user'            => $context['user'],
            'theme'           => $context['theme'],
            'allPlugins'      => $context['plugins'],
            'activePlugins'   => array_values($activePlugins),
            'inactivePlugins' => array_values($inactivePlugins),
            'totalPlugins'    => count($context['plugins']),
            'activeCount'     => count($activePlugins),
            'menus'           => $context['menus'],
            'currentPage'     => $context['currentPage'],
        ];
    }

    /**
     * Get site information.
     */
    private function getSiteInfo(): array
    {
        return [
            'name'    => get_bloginfo('name'),
            'url'     => site_url(),
            'adminUrl'=> admin_url(),
            'version' => get_bloginfo('version'),
            'language'=> get_locale(),
        ];
    }

    /**
     * Get current user information.
     */
    private function getUserInfo(): array
    {
        $user = wp_get_current_user();

        return [
            'displayName' => $user->display_name,
            'username'    => $user->user_login,
            'email'       => $user->user_email,
            'roles'       => $user->roles,
            'isAdmin'     => in_array('administrator', $user->roles, true),
        ];
    }
}
