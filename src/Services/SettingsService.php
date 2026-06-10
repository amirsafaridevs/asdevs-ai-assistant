<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Services;

/**
 * Manages plugin settings stored in wp_options.
 * This is configuration, NOT chat history — allowed per spec.
 */
class SettingsService
{
    private const OPTION_KEY = 'asdevs_ai_assistant_settings';

    /**
     * Available AI providers and their models.
     */
    public const PROVIDERS = [
        'openai' => [
            'name'     => 'OpenAI (ChatGPT)',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'models'   => [
                'gpt-5.5'       => 'GPT-5.5',
                'gpt-5.4'       => 'GPT-5.4',
                'gpt-5.4-mini'  => 'GPT-5.4 Mini',
                'gpt-5.4-nano'  => 'GPT-5.4 Nano',
                'o3'            => 'o3 (Reasoning)',
            ],
        ],
        'deepseek' => [
            'name'     => 'DeepSeek',
            'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
            'models'   => [
                'deepseek-v4-pro'   => 'DeepSeek V4 Pro',
                'deepseek-v4-flash' => 'DeepSeek V4 Flash',
            ],
        ],
        'gemini' => [
            'name'     => 'Google Gemini',
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'models'   => [
                'gemini-2.5-pro'       => 'Gemini 2.5 Pro',
                'gemini-2.5-flash'     => 'Gemini 2.5 Flash',
                'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite',
            ],
        ],
        'claude' => [
            'name'     => 'Anthropic Claude',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'models'   => [
                'claude-opus-4-8-20250514'   => 'Claude Opus 4.8',
                'claude-sonnet-4-20250514'   => 'Claude Sonnet 4',
                'claude-haiku-4-20250514'    => 'Claude Haiku 4',
                'claude-fable-5-20250514'    => 'Claude Fable 5',
            ],
            'note' => '⚠️ Claude uses a different API format. Use an OpenAI-compatible proxy or enable Custom Endpoint.',
        ],
    ];

    /**
     * Default settings.
     */
    private array $defaults = [
        'provider'           => 'openai',
        'model'              => 'gpt-5.4-mini',
        'api_key'            => '',
        'use_custom_endpoint' => false,
        'custom_endpoint'    => '',
    ];

    /**
     * Get all settings.
     */
    public function get(): array
    {
        $settings = get_option(self::OPTION_KEY, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        return array_merge($this->defaults, $settings);
    }

    /**
     * Save settings.
     */
    public function save(array $input): array
    {
        $current = $this->get();

        $settings = [
            'provider'            => sanitize_text_field($input['provider'] ?? $current['provider']),
            'model'               => sanitize_text_field($input['model'] ?? $current['model']),
            'use_custom_endpoint' => !empty($input['use_custom_endpoint']),
            'custom_endpoint'     => esc_url_raw($input['custom_endpoint'] ?? $current['custom_endpoint']),
        ];

        // Only update API key if a new one is provided (don't overwrite with empty)
        if (!empty($input['api_key'])) {
            $settings['api_key'] = sanitize_text_field($input['api_key']);
        } else {
            $settings['api_key'] = $current['api_key'];
        }

        // Validate provider
        if (!array_key_exists($settings['provider'], self::PROVIDERS)) {
            $settings['provider'] = $this->defaults['provider'];
        }

        // Validate model against provider
        $providerModels = self::PROVIDERS[$settings['provider']]['models'] ?? [];
        if (!array_key_exists($settings['model'], $providerModels)) {
            // Default to first model of the provider
            $settings['model'] = array_key_first($providerModels) ?: $this->defaults['model'];
        }

        update_option(self::OPTION_KEY, $settings, false);

        return $settings;
    }

    /**
     * Get the effective API endpoint.
     */
    public function getEndpoint(): string
    {
        $settings = $this->get();

        if (!empty($settings['use_custom_endpoint']) && !empty($settings['custom_endpoint'])) {
            return $settings['custom_endpoint'];
        }

        $provider = $settings['provider'];
        return self::PROVIDERS[$provider]['endpoint'] ?? self::PROVIDERS['openai']['endpoint'];
    }

    /**
     * Get the API key.
     */
    public function getApiKey(): string
    {
        $settings = $this->get();
        return $settings['api_key'] ?? '';
    }

    /**
     * Get the selected model.
     */
    public function getModel(): string
    {
        $settings = $this->get();
        return $settings['model'] ?? $this->defaults['model'];
    }

    /**
     * Get the selected provider slug.
     */
    public function getProvider(): string
    {
        $settings = $this->get();
        return $settings['provider'] ?? $this->defaults['provider'];
    }

    /**
     * Get all providers with their models (for frontend).
     */
    public function getProvidersData(): array
    {
        $result = [];
        foreach (self::PROVIDERS as $slug => $data) {
            $result[$slug] = [
                'slug'   => $slug,
                'name'   => $data['name'],
                'models' => $data['models'],
                'note'   => $data['note'] ?? '',
            ];
        }
        return $result;
    }

    /**
     * Get settings safe for frontend exposure (no API key).
     */
    public function getPublic(): array
    {
        $settings = $this->get();
        return [
            'provider'            => $settings['provider'],
            'model'               => $settings['model'],
            'use_custom_endpoint' => !empty($settings['use_custom_endpoint']),
            'custom_endpoint'     => $settings['custom_endpoint'],
            'has_api_key'         => !empty($settings['api_key']),
            'endpoint'            => $this->getEndpoint(),
            'providers'           => $this->getProvidersData(),
        ];
    }

    /**
     * Check if the plugin is configured (has an API key).
     */
    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * Get the default provider.
     */
    public function getDefaultProvider(): string
    {
        return $this->defaults['provider'];
    }
}
