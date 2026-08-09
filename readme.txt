=== ASDevs AI Assistant ===
Contributors: amirsafaridevs
Donate link: https://profiles.wordpress.org/amirsafaridevs/
Tags: ai, assistant, automation, admin, productivity
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free WordPress AI assistant and admin agent: manage your site in plain language. Discovers REST APIs live. Always free.

== Description ==

**ASDevs AI Assistant** is a free WordPress AI agent for site administrators. It lives inside your admin, learns what *this* site can actually do, and carries out the work — not just talks about it.

It is not a content chatbot pasted into WordPress. It is an **administrative AI assistant**: a colleague that reads your site’s live capabilities, respects your permissions, and turns plain-language requests into real actions on posts, users, settings, plugins, and anything else exposed through WordPress REST APIs.

**Always free.** There is no paid tier, no feature lock behind a upgrade wall, and no “pro” gate on core agent behaviour. You bring your own AI connection through WordPress Connectors; the plugin itself stays free forever under the GPL.

= Why this WordPress AI plugin is different =

Most “AI for WordPress” plugins help you write text. ASDevs AI Assistant helps you **run the site**.

* **Discovery, not a hardcoded feature list.** The agent reads your site’s REST API map — core, themes, and plugins — and treats that as its capability map. Install WooCommerce today and ask about orders today. Ship an in-house CRM that registers REST routes, and the agent can use them without a special integration from us.
* **Action, not directions.** “Go to Settings → General” is a failure mode. When something can be done through the APIs your site already exposes, the assistant does it and reports the result. When it cannot, it says so honestly and points you to the right admin screen.
* **Your permissions are the ceiling.** Only users who can manage the site open the assistant. Every read and write still runs as *you*. The agent cannot outrank an administrator’s own caps.
* **Safety enforced on the server.** Risky or irreversible changes need explicit confirmation. A hard “never” list blocks dangerous patterns no matter how the request is phrased. Model cleverness cannot bypass product rules.
* **Built for extenders.** Plugin and theme authors who register standard WordPress REST routes automatically widen what the agent can do. Admins can also define reusable **skills** — named prompts the agent follows for repeatable workflows.

= What you can do with this AI agent for WordPress =

Ask in any language. Short commands work as well as full sentences.

**Content and publishing**

* Create drafts, edit titles and body, assign categories and tags
* Schedule posts, move items, review recent drafts
* Work from the screen you are already on (“publish this”, “schedule this for tomorrow at nine”)

**Users and access**

* List users, create accounts, change roles (with confirmation when access rises)
* Never delete or elevate *your own* account through the agent

**Plugins, themes, and site health**

* Inspect which plugins are active, inactive, or outdated
* Activate or deactivate plugins when the APIs allow it
* Summarise site state instead of dumping raw menus

**Anything on your REST map**

* Forms, memberships, LMS, ecommerce, events, CRMs, custom post types — if they register discoverable REST routes the signed-in admin may call, they become agent capabilities
* No per-plugin configuration inside ASDevs AI Assistant for discovery to work

**Repeatable admin workflows (Skills)**

* Administrators define skills with a name, slug, short description, and prompt
* Invoke a skill when you want the agent to follow a house style or multi-step routine
* Skills are stored on your site and travel with it

**Ask mode and Agent mode**

* **Agent mode** — discover, read, and change the site (writes still pass risk policy)
* **Ask mode** — read-only answers from live site data; no writes proposed through the tools surface

**Memory and conversations**

* Conversation history stored on your own WordPress site, per administrator
* Optional persistent notes the agent may keep for site facts you want remembered
* Delete one conversation or everything; uninstall removes plugin data

= How the agent understands your WordPress site =

1. **Intent** — what you want done, not which menu path you remembered
2. **Capability match** — live REST discovery (`list_capabilities` / `describe_capability`) instead of a static “supported features” table
3. **Risk and permission check** — server-side policy before anything writes
4. **Execution** — generic `call_api` against the route WordPress already registered
5. **Verification** — report what actually happened, with links back into the admin when useful

That architecture is why a custom plugin you wrote last week can work with the agent on day one: **standard REST registration is the integration**.

= For plugin and theme developers =

You do not need a proprietary SDK to “support” this assistant.

* Register WordPress REST routes with clear schemas, args, and permission callbacks — the agent discovers them like any other client
* Prefer descriptive route schemas; the agent can load full parameter detail before calling
* Keep dangerous operations behind proper caps; the assistant will not invent permissions the user lacks
* Optionally shape agent behaviour with the `asdevs_ai_assistant_system_prompt` filter, or register extra AI provider adapters via `asdevs_ai_assistant_providers`
* Want a purpose-built surface for agents? Expose dedicated REST endpoints for common admin tasks; discovery will pick them up automatically

This makes ASDevs AI Assistant a practical **WordPress agent runtime** for ecosystems that already speak REST, without forcing every product to ship a private chatbot.

= Safety and trust =

Three risk levels, enforced in PHP — not only in the model prompt:

1. **Read** — runs immediately
2. **Reversible change** — runs and is reported clearly
3. **Risky / irreversible / bulk / public-facing / permission changes** — asks first, with impact and reversibility stated

Hard refusals include (among others): deleting your own account, raising your own role, changing the site URL, installing plugins or themes as code, wiping whole collections in one call, bulk messaging members, and editing site files.

= Free forever, private by design =

* The plugin is **free** under GPLv2 or later — forever
* Conversations stay on **your** WordPress database
* API keys live in **WordPress Connectors**, not in this plugin’s settings forms
* Nothing is sent to ASDevs servers
* No analytics, no telemetry, no tracking baked into the product
* You only pay whatever your chosen AI provider charges for the tokens you use

= Languages =

You can talk to the agent in **any language**. It replies in the language you write — that is a property of the AI model, not a limit of this plugin.

The plugin’s own interface language is **English**. A translation template (`.pot`) ships under `languages/`. Contributions of locale files through standard WordPress i18n (`.po` / `.mo`) are welcome.

= Requirements =

* WordPress 7.0 or later (uses core AI connectors and the WordPress AI Client)
* PHP 8.1 or later
* At least one AI connector configured under **Settings → Connectors** (OpenAI, Anthropic, Google, or another connector WordPress supports)
* Administrator capability (`manage_options`) to open and use the assistant

= What this plugin is not =

* Not a public-facing chatbot or support widget for visitors
* Not a bulk SEO content factory
* Not a replacement for careful human judgement on high-impact site changes
* Not tied to a single AI vendor

If you are looking for a **free WordPress AI assistant** that can help **manage WordPress**, automate admin work, and act as a real **AI agent** against your site’s APIs — this is that product.

== External services ==

This plugin uses the WordPress AI Client and the AI provider you connect under Settings → Connectors. It does not call AI provider APIs with its own hardcoded endpoints or store provider API keys. Nothing is sent anywhere until a connector is configured in WordPress, and nothing is ever sent to ASDevs servers. This plugin does not track users, and it does not send analytics or telemetry.

What is sent, and when: each time you write to the assistant, your message, the recent messages of that conversation, and the site information needed to answer it (for example a list of post titles you asked about, your display name and role, the site name and time zone) are sent through WordPress to the selected provider so it can produce a reply. When skills or memory notes are active for a turn, relevant prompt text may be included so the model can follow those instructions.

Common connectors and their policies (install only the ones you choose under Settings → Connectors):

* OpenAI — used to generate assistant replies when the OpenAI connector is selected.
  Terms of use: https://openai.com/policies/terms-of-use/
  Privacy policy: https://openai.com/policies/privacy-policy/
* Anthropic — used to generate assistant replies when the Anthropic connector is selected.
  Terms of service: https://anthropic.com/legal/consumer-terms
  Privacy policy: https://www.anthropic.com/legal/privacy
* Google — used to generate assistant replies when the Google connector is selected.
  Terms of service: https://ai.google.dev/gemini-api/terms
  Privacy policy: https://policies.google.com/privacy

If you connect a different provider through WordPress Connectors, review that provider's own terms and privacy policy before using it.

API keys are stored by WordPress Connectors on your own site. This plugin does not collect or store provider API keys.

Conversations and skills are stored on your own site. Conversations are attached to your user account and are visible only to you. You can delete a single conversation or all of them at any time from inside the assistant, and deleting the plugin removes plugin-owned data.

== Source code ==

Compiled front-end assets live in `assets/dist/`. The human-readable Vue/TypeScript source, build tooling, and Composer metadata ship with the plugin under `frontend/` and in `composer.json`.

Public development repository: https://github.com/amirsafaridevs/asdevs-ai-assistant

To rebuild the front-end assets from source:

1. `cd frontend`
2. `npm ci`
3. `npm run build`

The built files are written to `assets/dist/`.

== Installation ==

1. Install and activate **ASDevs AI Assistant** (WordPress 7.0 or later, PHP 8.1 or later).
2. Go to **Settings → Connectors** and connect an AI provider (Anthropic, OpenAI, Google, or another available connector).
3. Optionally open **AI Assistant** in the admin menu to choose which connector the assistant should prefer.
4. Open the floating assistant control on any admin screen and accept the terms gate when first prompted.
5. Start with a real task: “show my latest drafts”, “list inactive plugins”, or “create a draft about …”.

No setup wizard. No welcome tour. Activate and work.

== Frequently Asked Questions ==

= Is ASDevs AI Assistant really free forever? =

Yes. The plugin is free under GPLv2 or later. There is no premium unlock for the agent, discovery, skills, or risk policy. You may incur costs from the AI provider you connect in WordPress Connectors; that billing is between you and that provider.

= Is this a WordPress AI chatbot or a real admin agent? =

It is an **admin AI agent**. It can answer questions, but its design goal is to discover site capabilities and perform allowed actions through WordPress REST APIs. A chatbot that only drafts paragraphs is a different product category.

= How does it discover what my site can do? =

It builds a capability map from the site’s live REST route registry — the same map plugins and themes publish when they call `register_rest_route`. There is no hardcoded catalogue of “supported plugins” inside ASDevs AI Assistant. If a capability is not available through REST (or your user cannot reach it), the assistant will say so instead of pretending.

= Will it work with WooCommerce, membership plugins, LMS plugins, or my custom plugin? =

If those products expose usable REST APIs and your administrator can call them, the agent can list, describe, and call those routes. Popular plugins that follow WordPress REST conventions typically appear without extra setup. Custom in-house plugins work the same way when they register standard routes.

= Can other plugins add special APIs for the agent? =

Yes. The recommended path is ordinary WordPress REST API design: clear routes, schemas, and permission callbacks. Dedicated “agent-friendly” endpoints are welcome; discovery treats them like any other capability. Developers can also filter the system prompt (`asdevs_ai_assistant_system_prompt`) when tighter behavioural guidance is needed.

= What are Skills? =

Skills are administrator-defined reusable prompts stored on your site. Each skill has a title, slug (for example `/weekly-review`), optional description, and the instructions the agent should follow. Use them for house workflows: weekly content checks, publishing checklists, agency maintenance routines, reply-style guides, and similar repeats.

= What is the difference between Agent mode and Ask mode? =

**Agent mode** can propose reads and writes (writes still pass confirmation and never-rules). **Ask mode** is a read-only tool surface — useful when you want answers grounded in live site data without mutation.

= Can the AI do something I am not allowed to do myself? =

No. Only site administrators (`manage_options`) can open the assistant, and every action still runs through WordPress with that account’s permissions. Caps are enforced by WordPress and by this plugin’s serverside checks.

= Will it change something without asking? =

Reads run immediately. Reversible changes may run and are reported. Anything permanent, public-facing, permission-related, site-wide, bulk, or otherwise classified as confirmed-risk asks for approval first, with a clear description of impact and reversibility. Blanket “never ask again” confirmations are not accepted for high-risk work.

= What will it refuse even if I insist? =

Examples include deleting your own account, raising your own privileges, changing the site address, installing executable code (plugins/themes) through the agent, wiping entire collections in one call, bulk messaging members, and editing site files. These refusals are product rules on the server, not soft suggestions in the model prompt.

= I installed a new plugin. Do I have to reconfigure the assistant? =

Usually no. When the new plugin registers REST routes, they become part of the discovered capability map. If the plugin offers no programmatic API, the assistant cannot invent one.

= Does it add a chatbot to my public site? =

No. This plugin is an **admin-only** WordPress AI assistant. Visitors never see it.

= Which AI models does it support? =

Whatever you connect through **WordPress Connectors** and select for the assistant. The product is provider-agnostic by design. Model quality affects fluency and precision; safety boundaries do not rely on the model being perfect.

= Where are conversations stored? Who can see them? =

On your WordPress site, attached to your user. Other administrators do not browse your private conversation list through this plugin’s design. You can delete history anytime. Uninstall cleans up plugin data.

= Does ASDevs collect my site data or train on my chats? =

No. This plugin does not send data to ASDevs servers and does not include telemetry. Data leaves your site only through the WordPress AI connector path to the provider *you* configured, as described in External services.

= Which languages can I use with the assistant? =

You can ask questions and give instructions in **any language**. The model answers in the language you write.

The plugin UI itself is English. A `.pot` template ships under `languages/`. You can contribute or improve locales with the usual WordPress `.po` / `.mo` workflow.

= Does it replace the WordPress admin? =

No. It sits beside the admin as a colleague. Successful actions should remain inspectable in the normal screens, with deep links when helpful.

= Can agencies use this on client sites? =

Yes. The permission ceiling, confirmation policy, never-rules, on-site storage, and free GPL licence are intentionally aligned with professional trust requirements. Still review each client’s AI provider policy before connecting a model.

= Will this replace my need to learn WordPress? =

It reduces hunting through menus for common and uncommon tasks. You remain responsible for high-impact decisions the agent asks you to confirm. Treat it as a skilled operator, not as an unsupervised autopilot.

= What if the AI provider is down or rate-limited? =

You get a plain-language message that the AI service is unavailable, with a chance to retry. Your conversation history remains on the site.

= Can it install plugins or edit theme/core files? =

No. Installing code and editing site files are refused. The assistant can help you reach the relevant install or settings screens when that is the safe alternative.

= Is there a public REST API for building on top of the assistant? =

The assistant consumes your site’s REST map as a client. Its own admin endpoints power the chat UI (bootstrap, chat, actions, conversations, skills, memory, settings, terms). Extenders primarily integrate by improving *their* REST surfaces and optional filters documented in Source code / plugin hooks.

= Why WordPress 7.0? =

This release is built on WordPress core AI connectors and the AI Client so the plugin does not hold its own vendor API keys or hardcoded provider endpoints.

== Screenshots ==

1. The assistant opens with suggestions based on what is actually happening on your site.
2. A risky change asks first, and says exactly what it affects.
3. Results are shown as readable data with a link into the panel.
4. Choosing a WordPress AI connector for the assistant.
5. Administrator skills — reusable prompts for repeatable site workflows.
6. Ask mode for read-only answers grounded in live REST data.

== Changelog ==

= 1.0.0 =
* First public release of ASDevs AI Assistant — free WordPress AI agent for administrators.
* Uses WordPress 7 AI connectors and the core AI Client instead of storing its own API keys.
* Live discovery of site REST capabilities from core, plugins, and themes — no per-plugin hardcoding.
* Generic API tool surface: list capabilities, describe a route, call allowed methods.
* Agent mode and Ask (read-only) mode.
* Administrator-defined Skills for reusable workflows.
* Persistent on-site memory notes and per-user conversation history with real deletion.
* Three-level risk policy and hard never-rules enforced on the server, with confirmation tokens for risky writes.
* Terms acceptance gate, settings screen for preferred connector, floating assistant on every admin screen.
* Chat in any language; English UI with a shippable `.pot` for community translations.
* Clean uninstall of plugin-owned data.

== Upgrade Notice ==

= 1.0.0 =
First release. Requires WordPress 7.0, PHP 8.1, and an AI connector from Settings → Connectors. Free forever under GPLv2 or later.
