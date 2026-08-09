# ASDevs AI Assistant

**Free WordPress AI assistant and admin agent.** Manage your site in plain language. Discovers REST APIs live. Always free.

[![License: GPL v2 or later](https://img.shields.io/badge/License-GPLv2%20or%20later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Requires WordPress](https://img.shields.io/badge/WordPress-7.0%2B-blue.svg)](https://wordpress.org/)
[![Requires PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://www.php.net/)
[![Version](https://img.shields.io/badge/version-1.0.0-green.svg)](https://github.com/amirsafaridevs/asdevs-ai-assistant)

ASDevs AI Assistant is a colleague inside your WordPress admin. It learns what *this* site can actually do — by reading the live REST API map from core, plugins, and themes — and turns plain-language requests into real actions. It is not a content chatbot pasted into WordPress. It is an **administrative AI agent**.

**Always free.** No paid tier. No “pro” gate on core agent behaviour. You bring your own AI connection through WordPress Connectors; the plugin stays free forever under the GPL.

---

## Why this is different

Most “AI for WordPress” plugins help you write text. This one helps you **run the site**.

| Pillar | What it means |
| --- | --- |
| **Discovery** | Capability map comes from your site’s live REST routes — not a hardcoded feature list |
| **Action** | “Go to Settings → General” is a failure mode; when an API can do it, the agent does it |
| **Trust** | Your permissions are the ceiling; risk policy and never-rules run on the server |
| **Extensibility** | Any plugin/theme with standard REST routes widens the agent; admins can define Skills |

Install a shop plugin today and ask about orders today. Ship an in-house CRM that registers REST routes, and the agent can use them without a special integration from us.

---

## Features

### Live capability discovery

The agent builds its understanding from WordPress’s REST registry (`register_rest_route` surfaces from core, plugins, and themes). Tools:

- `list_capabilities` — what this site exposes right now
- `describe_capability` — full parameter schema for one route
- `call_api` — GET/POST/PUT/PATCH/DELETE against allowed routes

### Agent mode and Ask mode

- **Agent mode** — discover, read, and change the site (writes still pass risk policy)
- **Ask mode** — read-only answers grounded in live site data

### Skills

Administrators define reusable skills (name, slug, description, prompt) for house workflows — weekly reviews, publishing checklists, agency maintenance routines, reply-style guides, and more. Invoke them when you want the agent to follow a stored routine.

### Memory and conversations

- Per-administrator conversation history on your own WordPress site
- Optional persistent memory notes for site facts worth remembering
- Delete one conversation or everything; uninstall removes plugin-owned data

### Safety

Three risk levels, enforced in PHP — not only in the model prompt:

1. **Read** — runs immediately  
2. **Reversible change** — runs and is reported clearly  
3. **Risky / irreversible / bulk / public-facing / permission changes** — asks first  

Hard refusals include deleting your own account, raising your own role, changing the site URL, installing plugins/themes as code, wiping whole collections in one call, bulk messaging members, and editing site files.

### Languages

You can talk to the agent in **any language**. It replies in the language you write — that comes from the AI model, not from a two-language limit in this plugin.

The plugin UI is **English**. Translation files ship under `languages/` for other locales (including Persian). Contributions through standard WordPress i18n (`.pot` / `.po`) are welcome.

---

## What you can ask

Ask in any language. Short commands work as well as full sentences.

**Content** — create drafts, edit, categorize, schedule, review drafts from the screen you are on (“publish this”, “schedule this for tomorrow at nine”).

**Users** — list users, create accounts, change roles (with confirmation when access rises). Never delete or elevate *your own* account through the agent.

**Plugins and health** — inspect active/inactive/outdated plugins; activate or deactivate when APIs allow.

**Anything on your REST map** — forms, memberships, LMS, ecommerce, events, CRMs, custom post types — if they register discoverable REST routes the signed-in admin may call, they become agent capabilities.

---

## Requirements

| Requirement | Version / note |
| --- | --- |
| WordPress | 7.0+ (core AI connectors + AI Client) |
| PHP | 8.1+ |
| Capability | `manage_options` (administrators) |
| AI | At least one connector under **Settings → Connectors** |

---

## Installation

1. Install and activate the plugin.
2. Go to **Settings → Connectors** and connect a provider (OpenAI, Anthropic, Google, or another WordPress connector).
3. Optionally open **AI Assistant** in the admin menu to pick the preferred connector.
4. Open the floating assistant on any admin screen and accept the terms gate when first prompted.
5. Start with a real task: “show my latest drafts”, “list inactive plugins”, or “create a draft about …”.

No setup wizard. No welcome tour. Activate and work.

### From this repository

```bash
# Production-style install assumes Composer deps and built assets are present.
composer install --no-dev --optimize-autoloader

cd frontend
npm ci
npm run build
```

Built front-end assets are written to `assets/dist/`.

---

## For plugin and theme developers

You do not need a proprietary SDK.

1. Register WordPress REST routes with clear schemas, args, and permission callbacks — the agent discovers them like any other client.
2. Prefer descriptive schemas; the agent can load full parameter detail before calling.
3. Keep dangerous operations behind proper capabilities.
4. Want a purpose-built surface for agents? Expose dedicated REST endpoints for common admin tasks; discovery picks them up automatically.

### Filters

```php
/**
 * Shape the assistant instructions for this site / request.
 *
 * @param string               $prompt
 * @param array<string, mixed> $snapshot
 * @param array<string, mixed> $page
 * @param string               $mode        agent|ask
 * @param array<int, string>   $skill_slugs
 */
apply_filters( 'asdevs_ai_assistant_system_prompt', $prompt, $snapshot, $page, $mode, $skill_slugs );

/**
 * Register extra AI provider adapters.
 */
apply_filters( 'asdevs_ai_assistant_providers', $extra, $settings, $registry );

/**
 * Register additional service providers into the plugin container.
 */
apply_filters( 'asdevs_ai_assistant_service_providers', $providers );

/**
 * Control whether front-end assistant assets load on a given admin screen.
 */
apply_filters( 'asdevs_ai_assistant_should_load', true );
```

---

## How the agent works

1. **Intent** — what you want done  
2. **Capability match** — live REST discovery  
3. **Risk and permission check** — server-side policy before writes  
4. **Execution** — generic `call_api` against the registered route  
5. **Verification** — report what actually happened, with admin links when useful  

Architecture detail lives under `src/` (discovery, execution, risk policy, conversations, skills, REST controllers) and `frontend/` (Vue/TypeScript admin UI).

---

## Privacy and external services

- Conversations, skills, and memory stay on **your** WordPress site  
- API keys live in **WordPress Connectors**, not in this plugin’s own key forms  
- Nothing is sent to ASDevs servers  
- No analytics, telemetry, or tracking baked into the product  
- When you chat, your message, recent conversation context, and the site facts needed to answer are sent through WordPress to the **provider you connected**  

Review the provider’s own terms when you enable a connector (OpenAI, Anthropic, Google, etc.). Full disclosure for the WordPress.org directory lives in [`readme.txt`](readme.txt) under **External services**.

---

## What this plugin is not

- Not a public-facing chatbot or visitor support widget  
- Not a bulk SEO content factory  
- Not tied to a single AI vendor  
- Not an unsupervised autopilot — high-impact actions still ask you first  

---

## FAQ

<details>
<summary><strong>Is it really free forever?</strong></summary>

Yes. GPLv2 or later. No premium unlock for the agent, discovery, skills, or risk policy. You may still pay your AI provider for tokens.
</details>

<details>
<summary><strong>Chatbot or admin agent?</strong></summary>

Admin agent. It can answer questions, but the design goal is discovering site capabilities and performing allowed actions through REST APIs.
</details>

<details>
<summary><strong>Will it work with WooCommerce / LMS / my custom plugin?</strong></summary>

If they expose usable REST APIs and your administrator can call them, yes — without a special integration from us.
</details>

<details>
<summary><strong>Can other plugins add APIs for the agent?</strong></summary>

Yes. Ordinary `register_rest_route` design is the integration. Optional behavioural filters are listed above.
</details>

<details>
<summary><strong>Can it do something I cannot do myself?</strong></summary>

No. Only administrators open it, and every action runs as that user through WordPress permissions.
</details>

<details>
<summary><strong>Will it change things without asking?</strong></summary>

Reads run immediately. Reversible changes may run and are reported. Risky, irreversible, bulk, public-facing, or permission changes ask first.
</details>

<details>
<summary><strong>Does it add a chatbot to the public site?</strong></summary>

No. Admin-only.
</details>

<details>
<summary><strong>Where are conversations stored?</strong></summary>

On your WordPress site, per user. Delete anytime. Uninstall removes plugin-owned data.
</details>

<details>
<summary><strong>Which languages can I use?</strong></summary>

Any language for chat — the model answers in the language you write. The plugin UI is English; locale files under `languages/` cover other languages where available (including Persian).
</details>

<details>
<summary><strong>Can it install plugins or edit core files?</strong></summary>

No. Those are hard refusals. It can point you to the right admin screen instead.
</details>

More questions are answered in [`readme.txt`](readme.txt) for the WordPress.org listing.

---

## Development

```text
asdevs-ai-assistant/
├── asdevs-ai-assistant.php   # Bootstrap only
├── src/                      # PHP: discovery, AI, security, REST, skills, …
├── frontend/                 # Vue + TypeScript UI
├── assets/dist/              # Built front-end
├── tests/                    # PHPUnit (risk policy, never-rules, …)
├── readme.txt                # WordPress.org readme
└── README.md                 # This file
```

```bash
# PHP deps (dev)
composer install

# Front-end
cd frontend && npm ci && npm run build

# Tests (when configured in your environment)
vendor/bin/phpunit
```

WordPress.org packaging notes and product behaviour principles are documented for contributors in `plans/` when present in the development tree (not required at runtime).

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) if shipped, or <https://www.gnu.org/licenses/gpl-2.0.html>.

## Author

[amirsafaridevs](https://profiles.wordpress.org/amirsafaridevs/) · [GitHub](https://github.com/amirsafaridevs/asdevs-ai-assistant)

---

If you want a **free WordPress AI assistant** that can help **manage WordPress** as a real **admin AI agent** against your site’s APIs — this is that product.
