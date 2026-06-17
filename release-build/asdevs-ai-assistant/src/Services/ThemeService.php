<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Services;

class ThemeService
{
    /**
     * Get active theme information.
     */
    public function getTheme(): array
    {
        $theme = wp_get_theme();

        return [
            'name'       => $theme->get('Name'),
            'version'    => $theme->get('Version'),
            'template'   => $theme->get('Template') ?: $theme->get_stylesheet(),
            'stylesheet' => $theme->get_stylesheet(),
            'author'     => $theme->get('Author'),
        ];
    }
}
