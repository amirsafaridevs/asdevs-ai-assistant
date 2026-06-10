<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Services;

class PluginService
{
    /**
     * Return active plugins list.
     */
    public function getPlugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins    = get_plugins();
        $activePlugins = get_option('active_plugins', []);
        $result        = [];

        foreach ($allPlugins as $pluginFile => $pluginData) {
            $slug = dirname($pluginFile);
            if ($slug === '.') {
                $slug = basename($pluginFile, '.php');
            }

            $result[] = [
                'name'    => $pluginData['Name'],
                'slug'    => $slug,
                'version' => $pluginData['Version'],
                'active'  => in_array($pluginFile, $activePlugins, true),
            ];
        }

        return $result;
    }
}
