# ASDevs AI Assistant - MVP Architecture Specification

## Project Goal

Build a WordPress plugin called **"ASDevs AI Assistant"**.

The plugin adds a floating AI assistant inside the WordPress admin area.

The assistant acts as a WordPress GPS.

Users can ask questions such as:

* How can I change my site logo?
* Where can I edit WooCommerce checkout settings?
* How do I disable comments?
* Where is the Elementor settings page?

The assistant should:

1. Understand the current WordPress installation.
2. Detect active theme.
3. Detect active plugins.
4. Detect available admin menus.
5. Detect current admin page.
6. Guide the user to the correct settings page.
7. Highlight the required field or section.
8. Continue the conversation after page redirects.
9. Never modify WordPress settings automatically.
10. Only provide guidance and navigation.

The MVP must be read-only.

No write actions.

No automatic modifications.

No file editing.

No database write operations.

No code execution.

---

# Core Product Principles

1. AI is NOT allowed to access WordPress directly.
2. AI only interacts through Tools.
3. Backend exposes a controlled set of APIs.
4. Frontend communicates with AI.
5. AI decides which tool to call.
6. WordPress remains read-only.

---

# Tech Stack

## Backend

PHP 8.2+

WordPress Plugin

Composer

PSR-4 Autoloading

Dependency Injection Container

Service Providers

Singleton Application Bootstrap

WordPress REST API

---

## Frontend

Vue 3

TypeScript

Pinia

Vue Router

Vite

Floating Widget UI

---

## AI Layer

LangChain

OpenAI Compatible Models

Tool Calling

Structured Output

Conversation Memory (Frontend Only)

---

# Important Storage Rule

DO NOT use:

* wp_options
* custom tables
* post meta
* user meta
* transient storage

for chat history or conversation state.

All chat state must live in browser storage.

Use:

localStorage

Only.

---

# Chat Persistence

Store only the current conversation.

No conversation history system.

No chat archive.

No multi-chat support.

Provide:

New Chat button

When clicked:

* Clear all messages
* Clear all state
* Generate new session id
* Start fresh conversation

Storage Key:

asdevs-ai-assistant-session

Structure:

{
sessionId: string,
messages: [],
taskState: {},
navigationState: {}
}

---

# Backend Architecture

## Root Structure

src/

App.php

Container.php

Providers/

Services/

Controllers/

Contracts/

Support/

Bootstrap/

---

# App Singleton

App::instance()

Responsibilities:

* boot plugin
* register providers
* register services
* initialize container

Single entry point.

---

# Service Container

Custom lightweight DI container.

Support:

* singleton
* bind
* make

---

# Service Providers

## AdminServiceProvider

Registers:

* admin hooks
* admin assets
* widget container

---

## RestApiServiceProvider

Registers all REST endpoints.

---

## AssetServiceProvider

Registers Vue build assets.

---

## ContextServiceProvider

Registers WordPress context services.

---

# Services

## ThemeService

Responsibilities:

Get active theme information.

Output:

{
name,
version,
template,
stylesheet
}

---

## PluginService

Responsibilities:

Return active plugins.

Output:

[
{
name,
slug,
version,
active
}
]

---

## MenuService

Responsibilities:

Read:

global $menu

global $submenu

Output normalized admin menu tree.

---

## CurrentPageService

Responsibilities:

Detect:

* screen id
* page title
* page url

---

## ContextService

Aggregates:

theme

plugins

menus

current page

Produces context for AI.

---

# REST API

Namespace:

asdevs-ai-assistant/v1

---

GET /context

Returns:

{
theme,
plugins,
menus,
currentPage
}

---

GET /theme

---

GET /plugins

---

GET /menus

---

GET /current-page

---

POST /navigate

Input:

{
url
}

Returns:

{
success
}

Frontend performs redirect.

Backend never redirects.

---

# Frontend Architecture

frontend/

src/

components/

stores/

services/

agent/

tools/

router/

---

# Main UI

Floating assistant widget.

Visible on all admin pages.

Bottom-right position.

Persistent across admin pages.

---

# Components

AssistantButton

AssistantPanel

ChatWindow

ChatMessage

TypingIndicator

NavigationBanner

HighlightOverlay

---

# Pinia Stores

## ChatStore

messages

loading

sessionId

---

## ContextStore

theme

plugins

menus

currentPage

---

## NavigationStore

currentTask

targetPage

targetSelector

step

redirectPending

---

# Browser Persistence

Persist:

ChatStore

NavigationStore

Using localStorage.

Restore automatically on page load.

---

# Redirect Continuation System

This is a critical feature.

Example:

User asks:

"How do I change my site logo?"

AI decides:

Navigate to Customizer.

Before redirect:

Store:

{
task: "change_logo",
step: 2,
redirectPending: true,
targetUrl: "...",
targetSelector: "..."
}

in localStorage.

After page loads:

Widget boots.

Restore state.

Open assistant automatically.

Continue workflow.

Highlight element.

Show next instruction.

---

# Page Scanner

Frontend only.

No file reading.

No database reading.

No scraping PHP files.

Use DOM scanning.

Scan:

forms

inputs

buttons

labels

tables

tabs

headings

links

Output:

{
labels: [],
buttons: [],
headings: [],
inputs: []
}

This context can be provided to AI.

---

# Highlight System

Frontend only.

Input:

CSS selector.

Behavior:

1. Scroll element into view.
2. Add animated highlight border.
3. Add tooltip.
4. Add spotlight overlay.

Example:

{
selector: "#woocommerce_calc_taxes",
message: "Change this option."
}

---

# Navigation System

AI cannot navigate directly.

AI requests:

navigate_to_page

Tool returns:

{
url
}

Frontend performs redirect.

State is persisted before redirect.

---

# LangChain Architecture

Agent Type:

Tool Calling Agent

---

# Available Tools

get_theme

Returns active theme.

---

get_plugins

Returns active plugins.

---

get_menus

Returns admin menu tree.

---

get_current_page

Returns current page.

---

navigate_user

Requests redirect.

---

highlight_element

Requests highlight.

---

scan_current_page

Returns DOM summary.

---

# AI Rules

The AI must:

* Never claim access it does not have.
* Never modify WordPress.
* Never write files.
* Never write database records.
* Never activate/deactivate plugins.
* Never execute code.

The AI acts only as:

Guide

Navigator

Assistant

Teacher

---

# MVP Scope

Included:

✅ Theme Detection

✅ Plugin Detection

✅ Admin Menu Detection

✅ Current Page Detection

✅ Floating Assistant

✅ LangChain Agent

✅ Navigation

✅ Highlighting

✅ Page Scanner

✅ Redirect Continuation

✅ Browser Persistence

✅ New Chat

---

Not Included:

❌ File Reading

❌ Database Query Tool

❌ Code Analysis

❌ Plugin Configuration Changes

❌ Auto Fixes

❌ AI Actions

❌ Multi-Chat History

❌ User Analytics

❌ Remote Storage

❌ WordPress Database Persistence

---

# Build Requirements

Frontend must be fully built using Vite.

Production assets generated into:

assets/dist/

Plugin ships with compiled assets only.

No node_modules included.

Composer install required only for PHP dependencies.

Plugin must be production-ready and WordPress.org compatible.
