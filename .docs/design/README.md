# Tipovačka — Image generation prompts

This folder holds ready-to-paste prompts for generating visual assets with ChatGPT's image tool (GPT-4o image generation / DALL-E 3). Each `.md` file is **self-contained** — open it, copy the prompt block, paste into ChatGPT.

## Shared visual DNA

Every prompt in this folder carries the same core style tokens so outputs feel like one product:

- **Palette:** white background (`#FFFFFF`) as the canvas, deep navy (`#081e44`) as the primary structural color, and a vivid cyan-blue (`#149AD5`, roughly `rgb(20, 154, 213)`) as the accent / highlight. Navy and cyan should dominate; use any third color sparingly.
- **Aesthetic:** modern sport-tech, clean European design, editorial poise. Think *Premier League broadcast graphics meet Linear.app*, not American sports TV and not a casino.
- **Illustration style:** flat vector with subtle gradients and light geometric detail. Crisp edges. Slightly stylized, never photorealistic unless explicitly requested.
- **Mood:** confident, friendly, slightly playful, never rowdy.
- **Typography note:** when a piece needs Czech text (e.g. *Tipovačka*, *Turnaje*), **do not ask image gen to render it**. Leave clean space in the composition and set the type in Figma/CSS afterwards. DALL-E mangles Czech diacritics (ř, š, č, ž, ů) reliably.

## What to avoid across every prompt

- Gambling / casino tropes: poker chips, dice, roulette wheels, slot reels, neon "BET" signs, dollar symbols. This app is a social tipping game among friends, not a sportsbook.
- Generic stock imagery: cheering crowds from behind, blurred stadium photos, stock "businessman holding phone."
- Literal soccer ball clichés: giant football rolling across the page, grass textures, goal nets. Reference the sport **abstractly** (trajectory lines, score markers, bracket shapes, data rhythm).
- Heavy textures, drop shadows, "web 2.0" gloss, beveled buttons.
- Over-saturated rainbow gradients. Stay within the two-color brand system plus neutrals.
- Text inside the image (logos excepted, with caveats).

## Files in this folder

| File | Purpose |
|---|---|
| `logo.md` | Icon mark concepts + guidance for pairing with wordmark |
| `favicon.md` | Favicon + app icon + PWA manifest icon generation (derived from the logo) |
| `hero-landing.md` | Above-the-fold visual for the public homepage |
| `og-social-card.md` | 1200×630 OpenGraph card for link previews (WhatsApp, Messenger) |
| `how-it-works-steps.md` | 3-step illustration set for the "Jak to funguje" section |
| `empty-states.md` | Friendly illustrations for empty leaderboards / no-data screens |
| `winner-celebration.md` | End-of-tournament celebration / trophy moment |
| `claude-code-usage.md` | Implementation spec for Claude Code — where every asset lives, how it's rendered, accessibility, testing |

## Model target

Image prompts in this folder are tuned for **ChatGPT GPT 5.4** image generation. The structured `ROLE / OBJECTIVE / CANVAS / COLOR SYSTEM / STYLE / CONSTRAINTS / OUTPUT` format leverages its stronger instruction-following and session-continuity capabilities. Multi-image sets (how-it-works, empty states) include a shared **session-opening prompt** meant to be sent first so the model carries style consistency across subsequent scene prompts in the same session.

## Iteration tips

1. Generate 3–4 variants of each asset, pick the closest, then ask ChatGPT to *"refine this one: make X more Y, keep everything else"* rather than starting over.
2. Export at 2× the display size — `hero` should come out at least 2400px wide for Retina displays.
3. After generation, run a quick palette check in Figma. Anything that drifted from `#081e44` / `#149AD5` should be color-corrected before shipping.
4. Keep the raw generated files (pre-crop, pre-overlay) in a `design/raw/` subfolder for future re-layout.
