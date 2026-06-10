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

The assistant requires only `read` capability, which any logged-in WordPress user has by default.

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
