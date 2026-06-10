<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Controllers;

use ASDevs\AIAssistant\Services\SettingsService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Secure proxy for AI API calls.
 * The API key NEVER leaves the server.
 * Frontend sends messages → Backend forwards to AI → Returns response.
 */
class ChatProxyController
{
    private const NAMESPACE = 'asdevs-ai-assistant/v1';

    public function __construct(
        private SettingsService $settingsService,
    ) {}

    /**
     * Register the chat proxy route.
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/chat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'proxyChat'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'messages' => [
                    'required'    => true,
                    'type'        => 'array',
                    'description' => 'Array of chat messages',
                ],
                'tools' => [
                    'required'    => false,
                    'type'        => 'array',
                    'description' => 'Array of tool definitions (optional)',
                    'default'     => [],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/chat/stream', [
            'methods'             => 'POST',
            'callback'            => [$this, 'streamChat'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'messages' => [
                    'required'    => true,
                    'type'        => 'array',
                    'description' => 'Array of chat messages',
                ],
                'tools' => [
                    'required'    => false,
                    'type'        => 'array',
                    'description' => 'Array of tool definitions (optional)',
                    'default'     => [],
                ],
            ],
        ]);
    }

    /**
     * Permission check.
     */
    public function checkPermission(): bool
    {
        return current_user_can('read');
    }

    /**
     * POST /chat - proxy messages to the configured AI provider.
     */
    public function proxyChat(WP_REST_Request $request): WP_REST_Response
    {
        $messages = $request->get_param('messages');
        $tools    = $request->get_param('tools') ?? [];

        if (!is_array($messages) || empty($messages)) {
            return new WP_REST_Response([
                'error' => 'Messages array is required.',
            ], 400);
        }

        $apiKey    = $this->settingsService->getApiKey();
        $endpoint  = $this->settingsService->getEndpoint();
        $model     = $this->settingsService->getModel();
        $provider  = $this->settingsService->getProvider();

        if (empty($apiKey)) {
            return new WP_REST_Response([
                'error' => 'AI Assistant is not configured. Please set your API key in the settings.',
            ], 400);
        }

        // Build the request body
        $body = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 1000,
        ];

        if (!empty($tools)) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }

        // Set headers based on provider
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($provider === 'claude') {
            // Claude uses x-api-key header
            $headers['x-api-key'] = $apiKey;
            $headers['anthropic-version'] = '2023-06-01';

            // Claude requires a different body format
            // Extract system message and convert to Claude format
            $systemMessage = '';
            $claudeMessages = [];
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    $systemMessage = $msg['content'];
                } elseif (in_array($msg['role'], ['user', 'assistant'])) {
                    $claudeMessages[] = [
                        'role'    => $msg['role'],
                        'content' => $msg['content'],
                    ];
                }
            }

            $body = [
                'model'        => $model,
                'max_tokens'   => 1000,
                'messages'     => $claudeMessages,
            ];

            if (!empty($systemMessage)) {
                $body['system'] = $systemMessage;
            }

            // Remove unsupported fields for Claude
            unset($body['tools'], $body['tool_choice'], $body['temperature'], $body['messages']);
            $body['messages'] = $claudeMessages;
        } else {
            // OpenAI-compatible (OpenAI, DeepSeek, Gemini)
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        // Make the API call
        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'error' => 'AI API request failed: ' . $response->get_error_message(),
            ], 500);
        }

        $statusCode  = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $data         = json_decode($responseBody, true);

        if ($statusCode !== 200 || !$data) {
            $errorMsg = $data['error']['message'] ?? $responseBody;
            return new WP_REST_Response([
                'error' => "AI API error ({$statusCode}): {$errorMsg}",
            ], 502);
        }

        // If Claude response, convert back to OpenAI-compatible format
        if ($provider === 'claude' && isset($data['content'])) {
            $textContent = '';
            foreach ($data['content'] as $block) {
                if ($block['type'] === 'text') {
                    $textContent .= $block['text'];
                }
            }
            return new WP_REST_Response([
                'choices' => [
                    [
                        'message' => [
                            'role'    => 'assistant',
                            'content' => $textContent,
                        ],
                    ],
                ],
            ], 200);
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * POST /chat/stream - SSE streaming proxy.
     * Streams AI response chunks to the frontend in real-time.
     */
    public function streamChat(WP_REST_Request $request): void
    {
        $messages = $request->get_param('messages');
        $tools    = $request->get_param('tools') ?? [];

        if (!is_array($messages) || empty($messages)) {
            $this->sendSSE('error', 'Messages array is required.');
            $this->sendSSE('done', '');
            return;
        }

        $apiKey    = $this->settingsService->getApiKey();
        $endpoint  = $this->settingsService->getEndpoint();
        $model     = $this->settingsService->getModel();
        $provider  = $this->settingsService->getProvider();

        if (empty($apiKey)) {
            $this->sendSSE('error', 'AI Assistant is not configured.');
            $this->sendSSE('done', '');
            return;
        }

        // Build request body with streaming enabled
        $body = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 1000,
            'stream'      => true,
        ];

        if (!empty($tools)) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }

        // Headers
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($provider === 'claude') {
            $headers['x-api-key'] = $apiKey;
            $headers['anthropic-version'] = '2023-06-01';
            // Claude streaming is different - fall back to non-streaming for now
            $body['stream'] = false;
        } else {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        // Output SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Make the API call
        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            $this->sendSSE('error', 'API request failed: ' . $response->get_error_message());
            $this->sendSSE('done', '');
            return;
        }

        $statusCode  = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        if ($statusCode !== 200) {
            $this->sendSSE('error', "AI API error ({$statusCode})");
            $this->sendSSE('done', '');
            return;
        }

        // If streaming is not supported (Claude, or disabled), send full response
        if (empty($body['stream']) || $provider === 'claude') {
            $data = json_decode($responseBody, true);
            if ($data && isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];
                $this->sendSSE('token', $content);
            } elseif ($data && isset($data['content'])) {
                $text = '';
                foreach ($data['content'] as $block) {
                    if ($block['type'] === 'text') $text .= $block['text'];
                }
                $this->sendSSE('token', $text);
            }
            $this->sendSSE('done', '');
            return;
        }

        // Parse SSE stream from AI response
        $lines = explode("\n", $responseBody);
        $fullContent = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || !str_starts_with($line, 'data: ')) {
                continue;
            }

            $data = substr($line, 6);

            if ($data === '[DONE]') {
                break;
            }

            $chunk = json_decode($data, true);
            if (!$chunk) continue;

            $delta = $chunk['choices'][0]['delta'] ?? null;
            if (!$delta) continue;

            // Handle content streaming
            if (isset($delta['content'])) {
                $fullContent .= $delta['content'];
                $this->sendSSE('token', $delta['content']);
            }

            // Handle tool call streaming
            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $tc) {
                    $fn = $tc['function'] ?? [];
                    if (isset($fn['name'])) {
                        $this->sendSSE('tool_start', json_encode([
                            'id'   => $tc['id'] ?? '',
                            'name' => $fn['name'],
                        ]));
                    }
                    if (isset($fn['arguments'])) {
                        $this->sendSSE('tool_args', json_encode([
                            'id'  => $tc['id'] ?? '',
                            'args' => $fn['arguments'],
                        ]));
                    }
                }
            }
        }

        $this->sendSSE('done', '');
    }

    /**
     * Send an SSE event to the client.
     */
    private function sendSSE(string $event, string $data): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE protocol requires raw output; $event is hardcoded internally
        echo "event: {$event}\n";
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE protocol requires raw output
        echo "data: {$data}\n\n";
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
    }
}
