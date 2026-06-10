# ASDevs AI Assistant

> 🧭 **Read-only GPS for your WordPress site** — A floating AI assistant that guides you through settings, menus, and configuration in the WordPress admin.

[![WordPress Plugin Version](https://img.shields.io/badge/wordpress-v1.0.0-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 📖 Overview

**ASDevs AI Assistant** is a smart, floating AI companion for your WordPress admin dashboard. It acts like a GPS for your WordPress site — helping you navigate menus, find settings, and configure your site without needing to remember where everything is.

> ⚠️ **Read-Only by Design:** The assistant cannot create, modify, or delete any content on your site. It only provides guidance and navigation.

---

## ✨ Key Features

| Feature | Description |
|---------|-------------|
| 🫧 **Floating Chat Widget** | A sleek, always-accessible AI assistant button in the bottom-right corner of every admin page |
| 🧠 **AI-Powered Navigation** | Ask where a setting is, and the assistant will guide you there step-by-step |
| 🌐 **Context-Aware** | Understands your current page, installed plugins, active theme, and admin menus |
| 🔒 **Secure by Design** | Your API key never leaves the server — all AI requests are proxied through the WordPress backend |
| 🤖 **Multiple AI Providers** | Supports OpenAI, Claude, DeepSeek, Gemini, and custom OpenAI-compatible endpoints |
| ⚡ **Streaming Responses** | Real-time, token-by-token AI responses via Server-Sent Events (SSE) |
| 🎯 **Element Highlighting** | Visually highlights the exact field or section the user needs to find |
| 🔄 **Redirect Continuation** | Continues the conversation even after navigating to a different admin page |

---

## 🎬 How It Works

1. **Install & Activate** the plugin
2. Go to **AI Assistant → Settings** and configure your AI provider and API key
3. Click the sparkle ✨ button in the bottom-right corner of any admin page
4. Ask questions like:
   - *"Where can I change the site title?"*
   - *"How do I install a new plugin?"*
   - *"Where is the WooCommerce checkout settings page?"*
5. The assistant will guide you with step-by-step instructions and can even navigate you directly to the right page

---

## 📦 Installation

### From WordPress Admin

1. Go to **Plugins → Add New**
2. Search for "ASDevs AI Assistant"
3. Click **Install Now** and then **Activate**
4. Go to **AI Assistant → Settings** to configure your AI provider

### Manual Installation

```bash
cd wp-content/plugins/
git clone https://github.com/amirsafaridevs/asdevs-ai-assistant.git
cd asdevs-ai-assistant
composer install --no-dev
```

Then activate the plugin from **Plugins → Installed Plugins**.

---

## ⚙️ Configuration

Navigate to **AI Assistant → Settings** in your WordPress admin and configure:

| Setting | Description |
|---------|-------------|
| **AI Provider** | Choose from OpenAI, Claude, DeepSeek, Gemini, or Custom |
| **API Key** | Your provider's API key (stored securely, never exposed to frontend) |
| **Model** | Select the AI model (e.g., GPT-4o, Claude 3.5 Sonnet, etc.) |
| **Custom Endpoint** | For OpenAI-compatible custom providers |

---

## 🤖 Supported AI Providers

| Provider | Models |
|----------|--------|
| **OpenAI** | GPT-4, GPT-4o, GPT-4o-mini, GPT-3.5 Turbo |
| **Anthropic (Claude)** | Claude 3.5 Sonnet, Claude 3 Opus, Claude 3 Haiku |
| **DeepSeek** | DeepSeek V3, DeepSeek R1 |
| **Google Gemini** | Gemini 1.5 Pro, Gemini 1.5 Flash |
| **Custom** | Any OpenAI-compatible endpoint |

---

## 🏗️ Architecture

```
asdevs-ai-assistant/
├── assets/
│   ├── css/          # Widget styles
│   └── dist/         # Built Vue frontend (production)
├── frontend/          # Vue 3 + TypeScript frontend
│   └── src/
│       ├── components/   # UI components
│       ├── stores/       # Pinia state management
│       ├── services/     # API & utility services
│       └── agent/        # AI agent & tool definitions
├── src/               # PHP Backend
│   ├── Admin/         # Admin pages & hooks
│   ├── Controllers/   # REST API controllers
│   ├── Providers/     # Service providers
│   ├── Services/      # Business logic services
│   ├── Contracts/     # Interfaces & contracts
│   ├── App.php        # Plugin bootstrap
│   └── Container.php  # DI container
├── plans/             # Architecture & design docs
├── asdevs-ai-assistant.php  # Plugin entry point
└── composer.json
```

### Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | PHP 8.2+, WordPress Plugin API, PSR-4, Custom DI Container |
| **Frontend** | Vue 3, TypeScript, Pinia, Vite |
| **AI** | Tool-calling Agent, OpenAI-compatible API, SSE Streaming |
| **Persistence** | Browser `localStorage` only (no server-side chat storage) |

---

## 🔐 Security

- ✅ API keys are stored in the WordPress database and **never exposed** to the frontend
- ✅ All AI requests are **proxied through the backend**
- ✅ The assistant requires only `read` capability
- ✅ **Zero frontend exposure** of secrets or credentials
- ✅ **Read-only** — no write, delete, or modify operations

---

## ❓ FAQ

<details>
<summary><strong>Is my API key secure?</strong></summary>
Yes. Your API key is stored in the WordPress database and is never exposed to the frontend. All AI requests are proxied through the WordPress backend.
</details>

<details>
<summary><strong>Does this plugin modify my site content?</strong></summary>
No. The assistant is completely read-only. It cannot create, modify, or delete any content on your site.
</details>

<details>
<summary><strong>What permissions does the assistant need?</strong></summary>
The assistant requires only <code>read</code> capability, which any logged-in WordPress user has by default.
</details>

<details>
<summary><strong>Where is chat history stored?</strong></summary>
All chat state lives in your browser's <code>localStorage</code>. No conversation data is stored on the server. Clicking "New Chat" clears everything and starts fresh.
</details>

<details>
<summary><strong>Can I use my own AI endpoint?</strong></summary>
Yes! Choose the "Custom" provider and enter any OpenAI-compatible endpoint URL.
</details>

---

## 🧑‍💻 Development

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- npm

### Setup

```bash
# Clone the repository
git clone https://github.com/amirsafaridevs/asdevs-ai-assistant.git
cd asdevs-ai-assistant

# Install PHP dependencies
composer install

# Install frontend dependencies
cd frontend
npm install

# Start Vite dev server
npm run dev

# Build for production
npm run build
```

### Branch Strategy

- `main` — Stable, production-ready code
- `develop` — Active development branch

---

## 📝 Changelog

### 1.0.0
- 🎉 Initial release
- Floating AI assistant widget for WordPress admin
- AI-powered page navigation
- Support for OpenAI, Claude, DeepSeek, and Gemini
- Streaming AI responses via SSE
- Custom endpoint support
- Context-aware responses (current page, plugins, theme, menus)
- Element highlighting
- Redirect continuation system

---

## 📄 License

This project is licensed under the **GPL-2.0-or-later** License. See the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

## 👤 Author

**Amir Safari**

- GitHub: [@amirsafaridevs](https://github.com/amirsafaridevs)
- Website: [amirsafaridev.github.io](https://amirsafaridev.github.io/)

---

<p align="center">
  Made with ❤️ for the WordPress community
</p>
