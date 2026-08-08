=== ASDevs AI Assistant ===
Contributors: amirsafaridevs
Tags: assistant, admin, productivity, automation, ai
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A colleague inside your WordPress admin: tell it what you want and it does it, within your own permissions.

== Description ==

Every plugin adds its own menu. Every theme moves its settings. Something that is one sentence in your head — "schedule this post for tomorrow at nine" — turns into six clicks across two screens.

ASDevs AI Assistant closes that gap. Write what you want in plain language, in Persian or English, and it does the work on your site.

**It knows your site, not just WordPress.** The assistant discovers what your particular site can do by reading the site's own interface, live. Install a shop plugin today and you can ask about orders today — with no update to this plugin and no setup. If a plugin offers no programmatic access, the assistant says so honestly instead of pretending.

**It does the work, it does not just point at it.** "Go to Settings → General" is a failure, not an answer. When something genuinely cannot be done here, you get a link straight to the right screen.

**You stay in control.**

* Reading anything happens straight away.
* A change that can be undone happens straight away and is reported exactly.
* Anything risky or permanent — deleting, publishing, changing who can do what, changing site-wide settings, or changing many items at once — asks you first, and tells you what it affects and whether it can be undone.
* The assistant can never do anything you could not do yourself in the admin. Only site administrators can open it, and your permissions are its ceiling.
* Some things it will never do, whatever it is asked: delete your own account, raise your own access, change the site address, install code, write to site files, or message your members.

**It stays out of the way.** No setup wizard, no welcome screen, no notices, no emails, no badges. Activate it and go back to work; it is there when you want it.

= Languages =

Persian and English are both first-class, with full right-to-left support. The interface follows your admin language, and the assistant answers in whichever language you write in.

== External services ==

This plugin uses the WordPress AI Client and the AI provider you connect under Settings → Connectors. It does not call AI provider APIs with its own hardcoded endpoints or store provider API keys. Nothing is sent anywhere until a connector is configured in WordPress, and nothing is ever sent to ASDevs servers. This plugin does not track users, and it does not send analytics or telemetry.

What is sent, and when: each time you write to the assistant, your message, the recent messages of that conversation, and the site information needed to answer it (for example a list of post titles you asked about, your display name and role, the site name and time zone) are sent through WordPress to the selected provider so it can produce a reply.

Common connectors and their policies (install only the ones you choose under Settings → Connectors):

* OpenAI — used to generate assistant replies when the OpenAI connector is selected.
  Terms of use: https://openai.com/policies/terms-of-use/
  Privacy policy: https://openai.com/policies/privacy-policy/
* Anthropic — used to generate assistant replies when the Anthropic connector is selected.
  Terms of service: https://www.anthropic.com/legal/consumer-terms
  Privacy policy: https://www.anthropic.com/legal/privacy
* Google — used to generate assistant replies when the Google connector is selected.
  Terms of service: https://ai.google.dev/gemini-api/terms
  Privacy policy: https://policies.google.com/privacy

If you connect a different provider through WordPress Connectors, review that provider's own terms and privacy policy before using it.

API keys are stored by WordPress Connectors on your own site. This plugin does not collect or store provider API keys.

Conversations are stored on your own site, attached to your user account, and are visible only to you. You can delete a single conversation or all of them at any time from inside the assistant, and deleting the plugin removes all of it.

== Source code ==

Compiled front-end assets live in `assets/dist/`. The human-readable Vue/TypeScript source, build tooling, and Composer metadata ship with the plugin under `frontend/` and in `composer.json`.

Public development repository: https://github.com/amirsafaridevs/asdevs-ai-assistant

To rebuild the front-end assets from source:

1. `cd frontend`
2. `npm ci`
3. `npm run build`

The built files are written to `assets/dist/`.

== Installation ==

1. Install and activate the plugin (WordPress 7.0 or later).
2. Go to Settings → Connectors and connect an AI provider (Anthropic, OpenAI, Google, …).
3. Optionally open AI Assistant in the admin menu to choose which connector the assistant should prefer.
4. Open the assistant from the button in the corner of any admin screen.

== Frequently Asked Questions ==

= Can it do something I am not allowed to do myself? =

No. Only site administrators (users with `manage_options`) can open the assistant, and every action still runs through WordPress with that account's permissions.

= Will it change something without asking? =

It performs reversible changes and reports them exactly. Anything permanent, public-facing, permission-related, site-wide, or affecting several items at once asks for your confirmation first, and tells you what will happen and whether it can be undone.

= I installed a new plugin. Do I have to configure anything? =

No. The assistant re-reads what your site can do. If the new plugin exposes its features programmatically, they are available immediately.

= Does it work with my custom in-house plugin? =

If your plugin registers REST routes, yes — with no code written for it specifically.

= Does it add a chatbot to my public site? =

No. This plugin lives only in the admin area.

== Screenshots ==

1. The assistant opens with suggestions based on what is actually happening on your site.
2. A risky change asks first, and says exactly what it affects.
3. Results are shown as readable data with a link into the panel.
4. Choosing a WordPress AI connector for the assistant.

== Changelog ==

= 1.0.0 =
* First release.
* Uses WordPress 7 AI connectors and the core AI Client instead of storing its own API keys.
* Conversation window on every admin screen.
* Live discovery of what the site can do, with no per-plugin code.
* Three-level risk policy enforced on the server, with explicit confirmation for risky changes.
* Conversation history with real deletion.
* Persian and English, with full right-to-left support.

== Upgrade Notice ==

= 1.0.0 =
First release. Requires WordPress 7.0 and a connector from Settings → Connectors.
