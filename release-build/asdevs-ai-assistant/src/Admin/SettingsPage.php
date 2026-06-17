<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Admin;

use ASDevs\AIAssistant\Services\SettingsService;

class SettingsPage
{
    private const PAGE_SLUG = 'asdevs-ai-assistant-settings';
    private const MENU_SLUG = 'asdevs-ai-assistant';

    public function __construct(
        private SettingsService $settingsService,
    ) {}

    /**
     * Register the admin menu and page.
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_asdevs_ai_save_settings', [$this, 'handleSave']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Enqueue settings page assets.
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page slug check only
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if (!in_array($page, [self::MENU_SLUG, self::PAGE_SLUG], true)) {
            return;
        }

        wp_enqueue_style(
            'asdevs-ai-admin-settings',
            ASDEVS_AI_ASSISTANT_ASSETS_URL . 'css/admin-settings.css',
            [],
            ASDEVS_AI_ASSISTANT_VERSION
        );

        wp_enqueue_script(
            'asdevs-ai-admin-settings',
            ASDEVS_AI_ASSISTANT_ASSETS_URL . 'js/admin-settings.js',
            [],
            ASDEVS_AI_ASSISTANT_VERSION,
            true
        );

        $providers = SettingsService::PROVIDERS;
        wp_localize_script('asdevs-ai-admin-settings', 'asdevsAiSettings', [
            'defaultEndpoints' => array_map(static fn(array $provider): string => $provider['endpoint'], $providers),
        ]);
    }

    /**
     * Add menu item to WordPress admin.
     */
    public function addMenu(): void
    {
        add_menu_page(
            'ASDevs AI Assistant',
            'AI Assistant',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
            'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#a7aaad">'
                . '<path d="M12 2L9.5 9.5L2 12L9.5 14.5L12 22L14.5 14.5L22 12L14.5 9.5L12 2Z"/>'
                . '</svg>'
            ),
            81
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Settings',
            'Settings',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    /**
     * Handle form submission.
     */
    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('asdevs_ai_settings');

        $input = [
            'provider'            => isset($_POST['asdevs_provider']) ? sanitize_text_field(wp_unslash($_POST['asdevs_provider'])) : '',
            'model'               => isset($_POST['asdevs_model']) ? sanitize_text_field(wp_unslash($_POST['asdevs_model'])) : '',
            'api_key'             => isset($_POST['asdevs_api_key']) ? sanitize_text_field(wp_unslash($_POST['asdevs_api_key'])) : '',
            'use_custom_endpoint' => isset($_POST['asdevs_use_custom_endpoint']),
            'custom_endpoint'     => isset($_POST['asdevs_custom_endpoint']) ? esc_url_raw(wp_unslash($_POST['asdevs_custom_endpoint'])) : '',
        ];

        $this->settingsService->save($input);

        wp_redirect(add_query_arg(
            ['page' => self::PAGE_SLUG, 'saved' => '1'],
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Render the settings page.
     */
    public function render(): void
    {
        $settings = $this->settingsService->get();
        $providers = SettingsService::PROVIDERS;
        $currentProvider = $settings['provider'];
        $currentModels = $providers[$currentProvider]['models'] ?? [];
        $useCustomEndpoint = !empty($settings['use_custom_endpoint']);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a status flag set by our own redirect, not form processing
        $saved = isset($_GET['saved']) && $_GET['saved'] === '1';
        ?>
        <div class="wrap asdevs-settings-wrap">
            <div class="asdevs-settings-header">
                <div class="asdevs-settings-brand">
                    <div class="asdevs-settings-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L9.5 9.5L2 12L9.5 14.5L12 22L14.5 14.5L22 12L14.5 9.5L12 2Z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="asdevs-settings-title">ASDevs AI Assistant</h1>
                        <p class="asdevs-settings-subtitle">Configure your AI provider and connection settings</p>
                    </div>
                </div>
            </div>

            <?php if ($saved): ?>
                <div class="asdevs-notice asdevs-notice-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Settings saved successfully.
                </div>
            <?php endif; ?>

            <?php if (!$this->settingsService->isConfigured()): ?>
                <div class="asdevs-notice asdevs-notice-warning">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
                    </svg>
                    The assistant is not yet configured. Add your API key below to activate it.
                </div>
            <?php endif; ?>

            <div class="asdevs-settings-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="asdevs_ai_save_settings">
                    <?php wp_nonce_field('asdevs_ai_settings'); ?>

                    <!-- Step 1: Provider -->
                    <div class="asdevs-field-group">
                        <label class="asdevs-field-label">
                            <span class="asdevs-step-badge">1</span>
                            AI Provider
                        </label>
                        <p class="asdevs-field-help">Select which AI service to use for the assistant.</p>
                        <div class="asdevs-provider-grid" id="asdevs-provider-grid">
                            <?php foreach ($providers as $slug => $data): ?>
                                <label class="asdevs-provider-card <?php echo $currentProvider === $slug ? 'active' : ''; ?>">
                                    <input
                                        type="radio"
                                        name="asdevs_provider"
                                        value="<?php echo esc_attr($slug); ?>"
                                        <?php checked($currentProvider, $slug); ?>
                                        class="asdevs-provider-radio"
                                        data-models='<?php echo esc_attr(wp_json_encode($data['models'])); ?>'
                                    >
                                    <span class="asdevs-provider-name"><?php echo esc_html($data['name']); ?></span>
                                    <?php if (!empty($data['note'])): ?>
                                        <span class="asdevs-provider-note"><?php echo esc_html($data['note']); ?></span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 2: Model -->
                    <div class="asdevs-field-group">
                        <label class="asdevs-field-label" for="asdevs-model">
                            <span class="asdevs-step-badge">2</span>
                            Model
                        </label>
                        <p class="asdevs-field-help">Choose the AI model. Different models have different capabilities and costs.</p>
                        <select
                            name="asdevs_model"
                            id="asdevs-model"
                            class="asdevs-select"
                        >
                            <?php foreach ($currentModels as $modelSlug => $modelName): ?>
                                <option value="<?php echo esc_attr($modelSlug); ?>" <?php selected($settings['model'], $modelSlug); ?>>
                                    <?php echo esc_html($modelName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Step 3: API Key -->
                    <div class="asdevs-field-group">
                        <label class="asdevs-field-label" for="asdevs-api-key">
                            <span class="asdevs-step-badge">3</span>
                            API Key
                        </label>
                        <p class="asdevs-field-help">Your API key is stored securely and never exposed to the frontend.</p>
                        <div class="asdevs-api-key-wrap">
                            <input
                                type="password"
                                name="asdevs_api_key"
                                id="asdevs-api-key"
                                class="asdevs-input-text"
                                placeholder="<?php echo $this->settingsService->isConfigured() ? '•••••••• (leave blank to keep current)' : 'sk-...'; ?>"
                                autocomplete="off"
                            >
                            <button type="button" class="asdevs-toggle-visibility" id="asdevs-toggle-key" title="Show/Hide API Key">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Step 4: Custom Endpoint (Optional) -->
                    <div class="asdevs-field-group">
                        <div class="asdevs-toggle-row">
                            <div>
                                <label class="asdevs-field-label">
                                    <span class="asdevs-step-badge">4</span>
                                    Custom Endpoint
                                    <span class="asdevs-label-optional">Optional</span>
                                </label>
                                <p class="asdevs-field-help">
                                    Override the default API endpoint. Use this for proxies, custom deployments, or compatible services.
                                </p>
                            </div>
                            <label class="asdevs-toggle">
                                <input
                                    type="checkbox"
                                    name="asdevs_use_custom_endpoint"
                                    id="asdevs-use-custom-endpoint"
                                    class="asdevs-toggle-input"
                                    <?php checked($useCustomEndpoint); ?>
                                >
                                <span class="asdevs-toggle-slider"></span>
                            </label>
                        </div>
                        <div class="asdevs-custom-endpoint-body" id="asdevs-custom-endpoint-body" style="<?php echo $useCustomEndpoint ? '' : 'display:none;'; ?>">
                            <input
                                type="url"
                                name="asdevs_custom_endpoint"
                                id="asdevs-custom-endpoint"
                                class="asdevs-input-text"
                                placeholder="<?php echo esc_attr($providers[$currentProvider]['endpoint'] ?? 'https://api.example.com/v1/chat/completions'); ?>"
                                value="<?php echo esc_url($settings['custom_endpoint']); ?>"
                            >
                            <?php if (empty($settings['custom_endpoint'])): ?>
                                <p class="asdevs-endpoint-hint">
                                    Default: <code><?php echo esc_html($providers[$currentProvider]['endpoint'] ?? ''); ?></code>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="asdevs-field-group asdevs-submit-row">
                        <button type="submit" class="asdevs-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Current Configuration Summary -->
            <?php if ($this->settingsService->isConfigured()): ?>
                <div class="asdevs-settings-card asdevs-config-summary">
                    <h3 class="asdevs-summary-title">Current Configuration</h3>
                    <div class="asdevs-summary-grid">
                        <div class="asdevs-summary-item">
                            <span class="asdevs-summary-label">Provider</span>
                            <span class="asdevs-summary-value"><?php echo esc_html($providers[$currentProvider]['name'] ?? $currentProvider); ?></span>
                        </div>
                        <div class="asdevs-summary-item">
                            <span class="asdevs-summary-label">Model</span>
                            <span class="asdevs-summary-value"><?php echo esc_html($currentModels[$settings['model']] ?? $settings['model']); ?></span>
                        </div>
                        <div class="asdevs-summary-item">
                            <span class="asdevs-summary-label">API Key</span>
                            <span class="asdevs-summary-value asdevs-key-status">Configured ✓</span>
                        </div>
                        <div class="asdevs-summary-item">
                            <span class="asdevs-summary-label">Endpoint</span>
                            <span class="asdevs-summary-value asdevs-endpoint-value"><?php echo esc_html($this->settingsService->getEndpoint()); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
