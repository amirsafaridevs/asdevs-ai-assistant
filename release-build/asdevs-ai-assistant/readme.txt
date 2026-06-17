=== ASDevs AI Assistant ===
Contributors: amirsafaridevs
Tags: ai, assistant, admin, ai assistant, chatbot
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A floating AI assistant for WordPress admin that guides users through settings, menus, and configuration. Read-only GPS for your WordPress site.

== Description ==

ASDevs AI Assistant is a smart, floating AI companion for your WordPress admin dashboard. It acts like a GPS for your WordPress site — helping you navigate menus, find settings, and configure your site without needing to remember where everything is.

**Key Features:**

* **Floating Chat Widget** — A sleek, always-accessible AI assistant button in the WordPress admin
* **AI-Powered Navigation** — Ask the assistant where a setting is, and it will guide you there
* **Context-Aware** — The assistant understands your current page, installed plugins, theme, and admin menus
* **Secure by Design** — Your API key never leaves the server; all AI requests are proxied through the backend
* **Multiple AI Providers** — Supports OpenAI, Claude, DeepSeek, Gemini, and custom OpenAI-compatible endpoints
* **Streaming Responses** — Real-time, token-by-token AI responses via Server-Sent Events (SSE)
* **Zero Frontend Exposure** — No API keys or secrets are exposed to the browser

**How It Works:**

1. Install and activate the plugin
2. Go to **AI Assistant → Settings** and configure your AI provider and API key
3. Click the AI assistant button (sparkle icon) in the bottom-right corner of any admin page
4. Ask questions like "Where can I change the site title?" or "How do I install a new plugin?"
5. The assistant will guide you with step-by-step instructions and can even navigate you directly to the right page

== Installation ==

1. Upload the `asdevs-ai-assistant` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **AI Assistant → Settings** to configure your AI provider and API key
4. Start using the floating assistant from any admin page

== Frequently Asked Questions ==

= Which AI providers are supported? =

OpenAI (GPT-4, GPT-4o, GPT-4o-mini, GPT-3.5 Turbo), Claude (Anthropic), DeepSeek, Google Gemini, and any OpenAI-compatible custom endpoint.

= Is my API key secure? =

Yes. Your API key is stored in the WordPress database and is never exposed to the frontend. All AI requests are proxied through the WordPress backend.

= Does this plugin modify my site content? =

No. The assistant is completely read-only. It cannot create, modify, or delete any content on your site.

= What permissions does the assistant need? =

The assistant is available only to site administrators (`manage_options` capability). REST API endpoints and the chat widget require administrator access.

= Where is the frontend source code? =

The human-readable Vue 3 + TypeScript source for the compiled admin widget is included in the `frontend/` directory inside this plugin. The production build output lives in `assets/dist/`. To rebuild the frontend:

1. Install Node.js 18+ and npm
2. Run `cd frontend && npm install && npm run build`

The full source repository is also available at https://github.com/amirsafaridevs/asdevs-ai-assistant

== External services ==

This plugin connects to third-party AI providers to generate assistant responses. The site administrator chooses the provider and enters their own API key in **AI Assistant → Settings**. Requests are sent from your WordPress server only when an administrator uses the assistant chat.

**OpenAI**
Used for chat completions when OpenAI is selected as the provider.
Data sent: chat messages, selected model name, and tool definitions needed for navigation.
Terms of service: https://openai.com/policies/terms-of-use
Privacy policy: https://openai.com/policies/privacy-policy

**Anthropic (Claude)**
Used for chat completions when Claude is selected as the provider.
Data sent: chat messages, selected model name, and tool definitions needed for navigation.
Terms of service: https://www.anthropic.com/legal/terms
Privacy policy: https://www.anthropic.com/legal/privacy

**DeepSeek**
Used for chat completions when DeepSeek is selected as the provider.
Data sent: chat messages, selected model name, and tool definitions needed for navigation.
Terms of service: https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html
Privacy policy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html

**Google Gemini**
Used for chat completions when Gemini is selected as the provider.
Data sent: chat messages, selected model name, and tool definitions needed for navigation.
Terms of service: https://policies.google.com/terms
Privacy policy: https://policies.google.com/privacy

**Custom OpenAI-compatible endpoints**
If you enable a custom endpoint, the plugin sends the same chat request data to the URL you provide. You are responsible for reviewing that service's terms and privacy policy.

No data is sent to these services until the plugin is configured with an API key and an administrator sends a chat message.

== Screenshots ==

1. The floating AI assistant button in the WordPress admin
2. The settings page where you configure your AI provider
3. Chat window showing AI-guided navigation

== Changelog ==

= 1.0.0 =
* Initial release
* Floating AI assistant widget for WordPress admin
* AI-powered page navigation
* Support for OpenAI, Claude, DeepSeek, and Gemini
* Streaming AI responses via SSE
* Custom endpoint support
* Context-aware responses (current page, plugins, theme, menus)

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.
