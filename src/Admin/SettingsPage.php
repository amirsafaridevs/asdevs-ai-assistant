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
                                        data-models='<?php echo esc_attr(json_encode($data['models'])); ?>'
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

        <!-- Inline styles for settings page -->
        <style>
            /* Settings Page Styles */
            .asdevs-settings-wrap {
                max-width: 720px;
                margin: 20px 0;
                font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, sans-serif;
            }
            .asdevs-settings-header {
                margin-bottom: 24px;
            }
            .asdevs-settings-brand {
                display: flex;
                align-items: center;
                gap: 14px;
            }
            .asdevs-settings-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                background: linear-gradient(135deg, #007AFF, #5856D6);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .asdevs-settings-title {
                font-size: 22px;
                font-weight: 700;
                color: #111827;
                margin: 0 0 2px;
                padding: 0;
            }
            .asdevs-settings-subtitle {
                font-size: 13px;
                color: #6B7280;
                margin: 0;
            }
            .asdevs-settings-card {
                background: #fff;
                border: 1px solid #E5E7EB;
                border-radius: 16px;
                padding: 28px 32px;
                margin-bottom: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }
            .asdevs-field-group {
                margin-bottom: 24px;
            }
            .asdevs-field-group:last-child { margin-bottom: 0; }
            .asdevs-field-label {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                font-weight: 600;
                color: #111827;
                margin-bottom: 4px;
            }
            .asdevs-field-help {
                font-size: 12px;
                color: #6B7280;
                margin: 0 0 10px;
                line-height: 1.5;
            }
            .asdevs-step-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 22px;
                height: 22px;
                border-radius: 6px;
                background: #007AFF;
                color: white;
                font-size: 11px;
                font-weight: 700;
                flex-shrink: 0;
            }
            .asdevs-label-optional {
                font-size: 11px;
                font-weight: 400;
                color: #9CA3AF;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Provider grid */
            .asdevs-provider-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-top: 8px;
            }
            .asdevs-provider-card {
                display: flex;
                flex-direction: column;
                padding: 14px 16px;
                border: 2px solid #E5E7EB;
                border-radius: 12px;
                cursor: pointer;
                transition: all 150ms ease;
                background: #FAFBFC;
            }
            .asdevs-provider-card:hover {
                border-color: #007AFF;
                background: #F0F7FF;
            }
            .asdevs-provider-card.active {
                border-color: #007AFF;
                background: #F0F7FF;
                box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
            }
            .asdevs-provider-radio {
                display: none;
            }
            .asdevs-provider-name {
                font-size: 13px;
                font-weight: 600;
                color: #111827;
            }
            .asdevs-provider-note {
                font-size: 11px;
                color: #FF9F0A;
                margin-top: 4px;
                line-height: 1.4;
            }

            /* Select */
            .asdevs-select {
                width: 100%;
                max-width: 400px;
                padding: 10px 14px;
                border: 1px solid #D1D5DB;
                border-radius: 10px;
                font-size: 14px;
                font-family: inherit;
                background: #fff;
                color: #111827;
                outline: none;
                transition: border-color 150ms;
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 12px center;
                padding-right: 36px;
            }
            .asdevs-select:focus {
                border-color: #007AFF;
                box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
            }

            /* Text input */
            .asdevs-input-text {
                width: 100%;
                max-width: 400px;
                padding: 10px 14px;
                border: 1px solid #D1D5DB;
                border-radius: 10px;
                font-size: 14px;
                font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
                background: #fff;
                color: #111827;
                outline: none;
                transition: border-color 150ms;
            }
            .asdevs-input-text:focus {
                border-color: #007AFF;
                box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
            }
            .asdevs-input-text::placeholder {
                color: #9CA3AF;
            }
            .asdevs-api-key-wrap {
                position: relative;
                display: inline-block;
                max-width: 400px;
                width: 100%;
            }
            .asdevs-api-key-wrap .asdevs-input-text {
                width: 100%;
                padding-right: 40px;
            }
            .asdevs-toggle-visibility {
                position: absolute;
                right: 2px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                padding: 6px 8px;
                color: #9CA3AF;
                border-radius: 6px;
                transition: color 150ms;
            }
            .asdevs-toggle-visibility:hover {
                color: #6B7280;
            }
            .asdevs-endpoint-hint {
                font-size: 12px;
                color: #6B7280;
                margin-top: 6px;
            }
            .asdevs-endpoint-hint code {
                background: #F3F4F6;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 11px;
                word-break: break-all;
            }

            /* Buttons */
            .asdevs-btn-primary {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 24px;
                background: #007AFF;
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                font-family: inherit;
                cursor: pointer;
                transition: all 150ms;
            }
            .asdevs-btn-primary:hover {
                background: #0056CC;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,122,255,0.3);
            }
            .asdevs-submit-row {
                padding-top: 8px;
                border-top: 1px solid #F3F4F6;
            }

            /* Notices */
            .asdevs-notice {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 12px 16px;
                border-radius: 12px;
                margin-bottom: 16px;
                font-size: 13px;
                font-weight: 500;
            }
            .asdevs-notice-success {
                background: #ECFDF5;
                color: #065F46;
                border: 1px solid #A7F3D0;
            }
            .asdevs-notice-warning {
                background: #FFFBEB;
                color: #92400E;
                border: 1px solid #FDE68A;
            }

            /* Summary */
            .asdevs-config-summary {
                background: #F9FAFB;
                border: 1px solid #E5E7EB;
            }
            .asdevs-summary-title {
                font-size: 15px;
                font-weight: 600;
                color: #111827;
                margin: 0 0 16px;
                padding: 0;
            }
            .asdevs-summary-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .asdevs-summary-item {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            .asdevs-summary-label {
                font-size: 11px;
                font-weight: 500;
                color: #6B7280;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .asdevs-summary-value {
                font-size: 13px;
                font-weight: 600;
                color: #111827;
            }
            .asdevs-key-status {
                color: #059669 !important;
            }
            .asdevs-endpoint-value {
                font-size: 11px !important;
                font-family: 'SF Mono', 'Consolas', monospace;
                word-break: break-all;
                color: #6B7280 !important;
            }

            /* Toggle Switch */
            .asdevs-toggle-row {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 20px;
                margin-bottom: 10px;
            }
            .asdevs-toggle {
                position: relative;
                display: inline-block;
                width: 48px;
                height: 28px;
                flex-shrink: 0;
                cursor: pointer;
                margin-top: 2px;
            }
            .asdevs-toggle-input {
                opacity: 0;
                width: 0;
                height: 0;
                position: absolute;
            }
            .asdevs-toggle-slider {
                position: absolute;
                inset: 0;
                background: #D1D5DB;
                border-radius: 28px;
                transition: all 200ms ease;
            }
            .asdevs-toggle-slider::before {
                content: '';
                position: absolute;
                width: 22px;
                height: 22px;
                left: 3px;
                bottom: 3px;
                background: white;
                border-radius: 50%;
                transition: all 200ms cubic-bezier(0.175, 0.885, 0.32, 1.275);
                box-shadow: 0 1px 3px rgba(0,0,0,0.15);
            }
            .asdevs-toggle-input:checked + .asdevs-toggle-slider {
                background: #007AFF;
            }
            .asdevs-toggle-input:checked + .asdevs-toggle-slider::before {
                transform: translateX(20px);
            }
            .asdevs-toggle-input:focus-visible + .asdevs-toggle-slider {
                box-shadow: 0 0 0 3px rgba(0,122,255,0.25);
            }
            .asdevs-custom-endpoint-body {
                animation: asdevs-fade-down 200ms ease;
            }
            @keyframes asdevs-fade-down {
                from { opacity: 0; transform: translateY(-6px); }
                to { opacity: 1; transform: translateY(0); }
            }
        </style>

        <script>
        (function() {
            // Provider switch updates model dropdown
            const radios = document.querySelectorAll('.asdevs-provider-radio');
            const modelSelect = document.getElementById('asdevs-model');
            const endpointInput = document.getElementById('asdevs-custom-endpoint');
            const endpointHint = document.querySelector('.asdevs-endpoint-hint code');

            const defaultEndpoints = <?php echo json_encode(array_map(fn($p) => $p['endpoint'], $providers)); ?>;

            radios.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Update active card
                    document.querySelectorAll('.asdevs-provider-card').forEach(c => c.classList.remove('active'));
                    this.closest('.asdevs-provider-card').classList.add('active');

                    // Update models
                    const models = JSON.parse(this.dataset.models || '{}');
                    modelSelect.innerHTML = '';
                    Object.entries(models).forEach(([slug, name]) => {
                        const opt = document.createElement('option');
                        opt.value = slug;
                        opt.textContent = name;
                        modelSelect.appendChild(opt);
                    });

                    // Update endpoint hint
                    const provider = this.value;
                    if (endpointHint && defaultEndpoints[provider]) {
                        endpointHint.textContent = defaultEndpoints[provider];
                    }
                });
            });

            // Toggle API key visibility
            const toggleBtn = document.getElementById('asdevs-toggle-key');
            const apiKeyInput = document.getElementById('asdevs-api-key');
            if (toggleBtn && apiKeyInput) {
                toggleBtn.addEventListener('click', function() {
                    const isPassword = apiKeyInput.type === 'password';
                    apiKeyInput.type = isPassword ? 'text' : 'password';
                });
            }

            // Toggle custom endpoint section
            const customEndpointToggle = document.getElementById('asdevs-use-custom-endpoint');
            const customEndpointBody = document.getElementById('asdevs-custom-endpoint-body');
            if (customEndpointToggle && customEndpointBody) {
                customEndpointToggle.addEventListener('change', function() {
                    customEndpointBody.style.display = this.checked ? '' : 'none';
                });
            }
        })();
        </script>
        <?php
    }
}
