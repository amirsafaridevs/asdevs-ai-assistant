# ASDevs AI Assistant - UI/UX Architecture Specification

## Design Philosophy

This product should NOT look like a traditional WordPress plugin.

It should feel like a modern AI-native product.

Inspired by:

* iOS
* ChatGPT
* Raycast
* Linear
* Arc Browser

Core principles:

* Minimal
* Fast
* Clean
* Calm
* Focused
* Premium

Avoid:

* WordPress-style settings pages
* Heavy borders
* Large admin panels
* Old dashboard aesthetics

---

# Design Language

## Visual Style

Use:

Glassmorphism (subtle)

Soft Shadows

Rounded Corners

Layered Surfaces

Blur Effects

Smooth Animations

Depth

Modern Typography

---

## Border Radius

Buttons

12px

Cards

16px

Panel

24px

Chat Window

28px

---

## Shadows

Very soft shadows only.

Never use harsh shadows.

Example:

Small

0 8px 24px rgba(0,0,0,.08)

Large

0 20px 60px rgba(0,0,0,.12)

---

## Colors

Primary

#007AFF

iOS Blue

---

Success

#34C759

---

Warning

#FF9F0A

---

Danger

#FF3B30

---

Background

#F5F7FA

---

Surface

#FFFFFF

---

Text Primary

#111827

---

Text Secondary

#6B7280

---

Dark Mode Support

Required from day one.

---

# Floating Assistant

## Position

Bottom Right

Fixed

24px from edges

---

## Default State

Circular floating button

Size:

60px

Appearance:

Glass Surface

AI Icon

Soft Glow

Hover Animation

---

## Pulse Effect

When idle:

Very subtle breathing animation.

Not distracting.

---

## Notification State

If assistant has a suggestion:

Small blue badge.

Never use red badges.

---

# Assistant Panel

## Open Animation

Use iOS-style spring animation.

Duration:

250ms

---

## Size

Desktop

420px width

700px max height

---

Responsive

Minimum width:

360px

---

## Appearance

Floating card

Glass effect

Blur background

Rounded corners

Shadow depth

Feels detached from WordPress.

---

# Header

Contains:

Assistant Avatar

Title

Current Status

New Chat Button

Close Button

---

Example

ASDevs AI Assistant

Ready to help

---

# Chat Interface

Inspired by ChatGPT.

---

## User Messages

Right aligned

Blue bubble

White text

---

## Assistant Messages

Left aligned

White surface

Dark text

---

## Spacing

Generous whitespace.

Never cramped.

---

## Message Animation

Fade + Slide Up

150ms

---

# Typing Indicator

Three animated dots.

Smooth motion.

No spinners.

---

# Suggested Questions

Before first message show:

Cards

Examples:

How do I change my logo?

Where are WooCommerce settings?

How do I disable comments?

Where can I edit checkout fields?

Clicking a card sends the prompt.

---

# Navigation Experience

This is the product's killer feature.

---

## Navigation Card

When AI finds a location:

Show card.

Example:

WooCommerce Settings

Open Settings

---

## CTA Button

Take Me There

Primary button.

---

# Redirect Experience

Before redirect:

Show transition card.

Example:

Taking you to:

WooCommerce → Settings

---

After page load:

Automatically reopen assistant.

Restore conversation.

Continue task.

---

# Highlight Experience

Most important interaction.

---

## Spotlight Mode

Dim entire screen.

Keep target visible.

Like product tours.

---

## Highlight Ring

Animated blue ring.

Soft glow.

No yellow borders.

---

## Tooltip

Attached to highlighted element.

Contains:

Title

Description

Next Step

---

Example

Shipping Zone

Configure your shipping rules here.

---

# Page Awareness UI

Show current context.

Example:

Current Page

WooCommerce Settings

Theme

Astra

Plugins

24 Active

---

Presented as small chips.

---

# New Chat Experience

Clicking New Chat:

Show confirmation.

Start fresh session?

Cancel

New Chat

---

If confirmed:

Clear localStorage.

Generate new session.

Reset state.

---

# Empty State

Beautiful onboarding screen.

Logo

Short tagline

Prompt suggestions

---

Example

Ask anything about your WordPress site.

I can help you find settings, understand plugins, and navigate your admin panel.

---

# Loading States

Skeleton placeholders.

Never show blank screens.

---

# Accessibility

Keyboard Navigation

Focus States

ARIA Labels

Screen Reader Support

Reduced Motion Support

---

# Micro Interactions

Hover Elevation

Button Press Feedback

Smooth Transitions

Spring Animations

Contextual Feedback

---

# Performance Requirements

Initial Widget Load:

< 150KB gzipped

---

Time To Interactive:

< 1 second

---

60 FPS Animations

Required

---

All animations GPU accelerated.

Use transform and opacity only.

Avoid layout reflows.

---

# Final UX Goal

The user should feel:

"This is not a WordPress plugin."

"This feels like a native AI assistant built directly into WordPress."

The experience should resemble interacting with ChatGPT inside a premium iOS application rather than using a traditional WordPress admin tool.
