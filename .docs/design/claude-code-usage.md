# Tipovačka — Image asset integration spec

**Audience:** Claude Code, implementing the Tipovačka Symfony 8 frontend.
**Purpose:** wire the generated image assets (produced from the prompts in this folder) into the application. This document defines **where each image lives, where it's rendered, how it's referenced, and what accessibility and responsive behavior is expected.**

Read `SPEC.md` (parent folder) for the product brief, and the individual prompt files (`logo.md`, `hero-landing.md`, `og-social-card.md`, `how-it-works-steps.md`, `empty-states.md`, `winner-celebration.md`, `favicon.md`) for the source-of-truth on each image's intent.

---

## Asset storage strategy

Two storage locations, one principle:

- **`public/`** — assets fetched by URL from browsers without passing through the asset pipeline: favicons, the web manifest, OG image, robots/sitemap. These sit at well-known paths (`/favicon.ico`, `/apple-touch-icon.png`, `/og-default.png`).
- **`assets/images/`** — assets referenced from Twig templates and CSS. These flow through Symfony AssetMapper so they get content-hashed, gzip-compressed, and long-cached. Reference them in Twig with `{{ asset('images/hero/hero-desktop.svg') }}`.

### File tree to create

```
public/
├── favicon.ico
├── favicon.svg
├── apple-touch-icon.png
├── icon-192.png
├── icon-512.png
├── icon-maskable-512.png
├── safari-pinned-tab.svg
├── manifest.webmanifest
├── og-default.png                 # 1200×630 OG card (default)
├── og-default-square.png          # 1080×1080 variant
└── robots.txt

assets/
└── images/
    ├── logo/
    │   ├── logo-horizontal.svg    # icon + wordmark, horizontal lockup
    │   ├── logo-stacked.svg       # icon above wordmark
    │   └── logo-icon.svg          # icon only
    ├── hero/
    │   ├── hero-desktop.svg       # 1920×1080 (ideal: SVG; PNG fallback ok)
    │   ├── hero-desktop.png       # 2x raster fallback if SVG unavailable
    │   └── hero-portrait.svg      # 1200×1500 mobile variant
    ├── how-it-works/
    │   ├── step-1-create-group.svg
    │   ├── step-2-predict.svg
    │   └── step-3-climb.svg
    ├── empty-states/
    │   ├── empty-leaderboard.svg
    │   ├── empty-tournaments.svg
    │   ├── empty-matches.svg
    │   └── empty-search.svg
    └── winner/
        ├── winner-wide.svg        # 1600×900
        ├── winner-square.svg      # 1080×1080
        └── winner-personal.svg    # 1080×1080 single-user variant
```

Prefer SVG for every illustration. SVGs scale crisply on Retina, are tiny on the wire, and can be color-corrected via CSS if the accent ever shifts. If ChatGPT outputs PNG only, run through [SVGOMG](https://jakearchibald.github.io/svgomg/) after vector-tracing in Figma; do **not** ship raw PNG for hero or how-it-works.

### Naming conventions

- All lowercase, kebab-case.
- Filenames describe **content**, not position (`winner-wide`, not `results-page-image`).
- Variants suffix: `-desktop`, `-portrait`, `-square`, `-personal`.

---

## Per-asset integration

### 1. Logo

**Source files:** `assets/images/logo/logo-horizontal.svg`, `logo-stacked.svg`, `logo-icon.svg`.

**Where it renders:**

- Site header (every authenticated and public page): horizontal lockup, height 32px desktop / 28px mobile, linking to `/` (unauthenticated) or `/nastenka` (authenticated).
- Email templates (verification, password reset, invitation): horizontal lockup, height 40px, inlined as base64 or referenced via absolute URL to `public/email-logo.png` (PNG for email client compatibility — **not** SVG).
- Footer: horizontal lockup in muted opacity (80%).
- Favicon + app icons: handled separately (see Favicon section below).

**Implementation:**

Create a reusable Twig component `templates/components/Brand/Logo.html.twig`:

```twig
{# @param variant 'horizontal'|'stacked'|'icon' — defaults to 'horizontal' #}
{# @param height int — defaults to 32 #}
<a href="{{ path(app.user ? 'app_dashboard' : 'app_home') }}" class="inline-flex items-center" aria-label="Tipovačka — domovská stránka">
    <img
        src="{{ asset('images/logo/logo-' ~ (variant|default('horizontal')) ~ '.svg') }}"
        alt="Tipovačka"
        height="{{ height|default(32) }}"
        style="height: {{ height|default(32) }}px; width: auto;"
        loading="eager"
        decoding="async"
    >
</a>
```

Render in `base.html.twig` header:

```twig
<twig:Brand:Logo variant="horizontal" :height="32" />
```

**Accessibility:** alt text `"Tipovačka"`. The link wrapper carries the full `aria-label`. Do not add both — redundant for screen readers.

---

### 2. Hero (landing page)

**Source files:** `assets/images/hero/hero-desktop.svg`, `hero-portrait.svg`.

**Where it renders:** Only on `/` (public homepage), inside the hero component.

**Implementation:**

Use the `<picture>` element for art-direction (different composition per breakpoint, not just different sizes):

```twig
<section class="relative overflow-hidden bg-white">
    <picture>
        <source media="(max-width: 767px)" srcset="{{ asset('images/hero/hero-portrait.svg') }}">
        <source media="(min-width: 768px)" srcset="{{ asset('images/hero/hero-desktop.svg') }}">
        <img
            src="{{ asset('images/hero/hero-desktop.svg') }}"
            alt=""
            role="presentation"
            class="absolute inset-0 h-full w-full object-cover object-right"
            loading="eager"
            fetchpriority="high"
            decoding="async"
        >
    </picture>

    <div class="relative mx-auto max-w-7xl px-6 py-24 lg:px-8 lg:py-32">
        <div class="max-w-2xl">
            <h1 class="text-5xl font-semibold tracking-tight text-[#081e44] sm:text-6xl">
                Tipuj zápasy. Poraz kamarády.
            </h1>
            <p class="mt-6 text-xl text-[#081e44]/70">
                Založ skupinu, zvi přátele a bojujte o prvenství v tabulce.
            </p>
            <div class="mt-10 flex items-center gap-4">
                <a href="{{ path('app_group_create') }}" class="rounded-md bg-[#149AD5] px-5 py-3 text-base font-semibold text-white hover:bg-[#0f84b8]">
                    Vytvořit skupinu
                </a>
                <a href="{{ path('app_tournaments_public') }}" class="text-base font-semibold text-[#081e44] hover:text-[#149AD5]">
                    Procházet turnaje →
                </a>
            </div>
        </div>
    </div>
</section>
```

**Key details:**

- `alt=""` + `role="presentation"` because the illustration is decorative — the headline carries the semantic meaning.
- `fetchpriority="high"` and `loading="eager"` because the hero is above the fold.
- The overlay copy sits on the left half, the illustration fills the right half via `object-right`.

---

### 3. OG / social share card

**Source files:** `public/og-default.png`, `public/og-default-square.png`.

**Where it renders:** Meta tags in `templates/base.html.twig` `<head>`.

**Implementation:**

Add to the base layout:

```twig
{# Default OG + Twitter card (can be overridden per-page) #}
<meta property="og:site_name" content="Tipovačka">
<meta property="og:type" content="{{ og_type|default('website') }}">
<meta property="og:title" content="{{ og_title|default('Tipovačka — Tipuj zápasy. Poraz kamarády.') }}">
<meta property="og:description" content="{{ og_description|default('Založ skupinu, zvi přátele a bojujte o prvenství v tabulce.') }}">
<meta property="og:url" content="{{ og_url|default(url('app_home')) }}">
<meta property="og:image" content="{{ og_image|default(absolute_url(asset('og-default.png'))) }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="cs_CZ">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ og_title|default('Tipovačka') }}">
<meta name="twitter:description" content="{{ og_description|default('Tipuj zápasy. Poraz kamarády.') }}">
<meta name="twitter:image" content="{{ og_image|default(absolute_url(asset('og-default.png'))) }}">
```

**Per-page overrides:** child templates set the `og_title`, `og_description`, `og_image` Twig variables via `{% block og_meta %}` or a dedicated Twig variable bag. Tournament-specific share cards pass a per-tournament rendered image URL here (phase 2 — leave a TODO comment).

**Important:** OG images must be served as absolute URLs with valid HTTPS. Do **not** rely on relative URLs — WhatsApp and Facebook reject them.

---

### 4. How-it-works (3 steps)

**Source files:** `assets/images/how-it-works/step-1-create-group.svg`, `step-2-predict.svg`, `step-3-climb.svg`.

**Where it renders:** On `/` homepage, below the hero, inside a "Jak to funguje" section.

**Implementation:**

```twig
<section class="bg-white py-24 sm:py-32">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-semibold tracking-tight text-[#081e44] sm:text-4xl">Jak to funguje</h2>
        </div>
        <div class="mx-auto mt-16 grid max-w-5xl grid-cols-1 gap-x-8 gap-y-16 md:grid-cols-3">
            {% set steps = [
                { img: 'step-1-create-group.svg', title: 'Vytvoř skupinu', body: 'Založ soukromou skupinu a pozvi kamarády přes odkaz nebo PIN.' },
                { img: 'step-2-predict.svg',     title: 'Tipuj zápasy',   body: 'Zadej svůj tip na každý zápas až do výkopu.' },
                { img: 'step-3-climb.svg',       title: 'Získej body',    body: 'Správné tipy ti nasbírají body a vyženou tě na špici tabulky.' }
            ] %}
            {% for step in steps %}
                <div class="flex flex-col items-center text-center">
                    <img
                        src="{{ asset('images/how-it-works/' ~ step.img) }}"
                        alt=""
                        role="presentation"
                        class="h-48 w-48"
                        loading="lazy"
                        decoding="async"
                    >
                    <h3 class="mt-6 text-xl font-semibold text-[#081e44]">{{ step.title }}</h3>
                    <p class="mt-2 text-base text-[#081e44]/70">{{ step.body }}</p>
                </div>
            {% endfor %}
        </div>
    </div>
</section>
```

**Accessibility:** the illustrations are decorative (the headline + body carry the meaning), so `alt=""` + `role="presentation"`.

---

### 5. Empty states

**Source files:** `assets/images/empty-states/empty-leaderboard.svg`, `empty-tournaments.svg`, `empty-matches.svg`, `empty-search.svg`.

**Where they render:**

| Scene | Component | Template |
|---|---|---|
| `empty-leaderboard.svg` | Group leaderboard when no guesses exist | `templates/group/leaderboard.html.twig` |
| `empty-tournaments.svg` | Dashboard when user has no groups | `templates/dashboard/index.html.twig` |
| `empty-matches.svg` | Tournament detail when no matches | `templates/tournament/show.html.twig` |
| `empty-search.svg` | Public tournament search with no results | `templates/tournament/search.html.twig` |

**Implementation:** build one reusable `EmptyState` Twig component so the look stays consistent.

Create `templates/components/EmptyState.html.twig`:

```twig
{# @param illustration 'leaderboard'|'tournaments'|'matches'|'search' #}
{# @param title string #}
{# @param body string #}
{# @param ctaLabel ?string — optional primary CTA label #}
{# @param ctaPath ?string — optional primary CTA path #}
{# @param secondaryLabel ?string #}
{# @param secondaryPath ?string #}
<div class="mx-auto flex max-w-md flex-col items-center py-16 text-center">
    <img
        src="{{ asset('images/empty-states/empty-' ~ illustration ~ '.svg') }}"
        alt=""
        role="presentation"
        class="h-40 w-40"
        loading="lazy"
        decoding="async"
    >
    <h3 class="mt-6 text-xl font-semibold text-[#081e44]">{{ title }}</h3>
    <p class="mt-2 text-base text-[#081e44]/70">{{ body }}</p>
    {% if ctaLabel and ctaPath %}
        <div class="mt-6 flex items-center gap-3">
            <a href="{{ ctaPath }}" class="rounded-md bg-[#149AD5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0f84b8]">
                {{ ctaLabel }}
            </a>
            {% if secondaryLabel and secondaryPath %}
                <a href="{{ secondaryPath }}" class="text-sm font-semibold text-[#081e44] hover:text-[#149AD5]">
                    {{ secondaryLabel }}
                </a>
            {% endif %}
        </div>
    {% endif %}
</div>
```

Then use it per screen. Example for the empty leaderboard:

```twig
{# In leaderboard.html.twig, when the collection is empty #}
<twig:EmptyState
    illustration="leaderboard"
    title="Tabulka je zatím prázdná"
    body="Jakmile první člen zadá tip, objeví se tady žebříček."
    {% if is_granted('MANAGE', group) %}
    ctaLabel="Zkopírovat pozvánku"
    ctaPath="{{ path('app_group_invite', { id: group.id }) }}"
    {% endif %}
/>
```

Conditional CTAs (e.g. "Zkopírovat pozvánku" only visible to owners) are gated with `is_granted()` calls against the `GroupVoter`.

---

### 6. Winner celebration

**Source files:** `assets/images/winner/winner-wide.svg`, `winner-square.svg`, `winner-personal.svg`.

**Where it renders:** On the tournament-results screen (e.g. `/turnaj/{slug}/vysledky`), and on the user's personal-result share page.

**Implementation:**

```twig
<section class="bg-white py-16 sm:py-24">
    <div class="mx-auto max-w-5xl px-6 lg:px-8">
        <picture>
            <source media="(max-width: 767px)" srcset="{{ asset('images/winner/winner-square.svg') }}">
            <source media="(min-width: 768px)" srcset="{{ asset('images/winner/winner-wide.svg') }}">
            <img
                src="{{ asset('images/winner/winner-wide.svg') }}"
                alt="Oslavná ilustrace vítěze turnaje"
                class="mx-auto w-full max-w-3xl"
                loading="lazy"
                decoding="async"
            >
        </picture>

        <div class="mt-8 text-center">
            <h1 class="text-4xl font-bold text-[#081e44]">
                Gratulujeme, <span class="text-[#149AD5]">{{ winner.nickname }}</span>!
            </h1>
            <p class="mt-4 text-lg text-[#081e44]/70">
                {{ winner.nickname }} vyhrává turnaj <strong>{{ tournament.name }}</strong> s {{ winner.points }} body.
            </p>
            <div class="mt-8 flex justify-center gap-3">
                <button data-controller="share" data-action="share#share" class="rounded-md bg-[#149AD5] px-5 py-3 text-base font-semibold text-white hover:bg-[#0f84b8]">
                    Sdílet výsledek
                </button>
                <a href="{{ path('app_group_leaderboard', { id: group.id }) }}" class="rounded-md border border-[#081e44] px-5 py-3 text-base font-semibold text-[#081e44] hover:bg-[#081e44]/5">
                    Zobrazit celou tabulku
                </a>
            </div>
        </div>
    </div>
</section>
```

**Accessibility:** the winner illustration is informative (it's the celebratory moment), so alt text `"Oslavná ilustrace vítěze turnaje"` — not empty-alt.

**Share button:** wire a Stimulus controller `shareController` that calls `navigator.share()` with title, text, and URL. Gracefully degrade to copy-link on browsers without the Web Share API.

---

### 7. Favicon + manifest

**Source files:** all the files listed in `public/` under "File tree to create" above.

**Implementation:** in `templates/base.html.twig` `<head>`, before the closing `</head>`:

```twig
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="mask-icon" href="{{ asset('safari-pinned-tab.svg') }}" color="#081e44">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#081e44">
<meta name="msapplication-TileColor" content="#081e44">
```

**`public/manifest.webmanifest` contents:**

```json
{
    "name": "Tipovačka",
    "short_name": "Tipovačka",
    "description": "Tipuj zápasy. Poraz kamarády.",
    "lang": "cs",
    "start_url": "/nastenka",
    "display": "standalone",
    "background_color": "#FFFFFF",
    "theme_color": "#081e44",
    "icons": [
        { "src": "/icon-192.png", "sizes": "192x192", "type": "image/png", "purpose": "any" },
        { "src": "/icon-512.png", "sizes": "512x512", "type": "image/png", "purpose": "any" },
        { "src": "/icon-maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
    ]
}
```

Note: the SPEC explicitly rules PWA install out of v1, so keep the manifest lean but present. Browsers fall back gracefully — adding it now costs nothing and makes future PWA-isation trivial.

---

## Cross-cutting concerns

### Alt text policy (Czech)

- **Decorative illustrations** (hero, how-it-works, empty states): `alt=""` + `role="presentation"`. The surrounding headline + body already carry meaning.
- **Informative illustrations** (winner celebration): Czech alt text describing the content.
- **Logo:** `alt="Tipovačka"`.
- **OG image:** no alt text — meta images don't take alt.

### Lazy loading

- `loading="eager"` + `fetchpriority="high"` only on the logo in the header and the hero image (above the fold on `/`).
- Every other image: `loading="lazy"` + `decoding="async"`.

### Print styles

Tournament results pages get printed / screenshotted. Add to the site-wide CSS:

```css
@media print {
    .no-print { display: none !important; }
    img { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}
```

Hero + how-it-works illustrations should be hidden from print (`class="no-print"`) because they don't add value on paper. Winner illustration stays visible.

### Reduced motion

The winner celebration may gain confetti animation in a future iteration. Wrap any such animation in:

```css
@media (prefers-reduced-motion: reduce) {
    .confetti-animation { animation: none !important; }
}
```

### Color-correction safety net

SVG illustrations pasted from ChatGPT output occasionally drift slightly off the brand palette (e.g. `#151EA5` instead of `#149AD5`). Before shipping each SVG, run a find-and-replace inside the SVG source to normalize:

- Any color close to `#081e44` → exactly `#081e44`.
- Any color close to `#149AD5` → exactly `#149AD5`.

Document this as a step in the design-asset-ingest routine.

---

## Testing checklist

1. **Lighthouse:** run a Lighthouse audit on `/`. Performance ≥ 95, Accessibility ≥ 100, Best Practices ≥ 100, SEO ≥ 100.
2. **OG preview:** paste a production URL into [opengraph.xyz](https://www.opengraph.xyz) and verify the card renders on Facebook, WhatsApp, Twitter, LinkedIn.
3. **Favicon audit:** run [realfavicongenerator.net checker](https://realfavicongenerator.net/favicon_checker) on a deployed URL.
4. **Broken assets:** in development, enable Symfony's asset version mismatch warning — a missing image produces a loud error instead of a silent 404.
5. **Responsive test:** at 360px, 768px, 1280px, 1920px — verify the hero crops cleanly and the empty states stay centered.
6. **Accessibility:** run axe DevTools against each page that uses an illustration; expect zero violations on alt text and roles.
7. **Print preview:** Chrome → File → Print Preview on `/turnaj/{slug}/vysledky` — the winner illustration should print readably in grayscale.
8. **Reduced motion:** emulate `prefers-reduced-motion` in DevTools and verify no animations play.

---

## Implementation order (recommended)

1. File-tree scaffolding + AssetMapper configuration for `assets/images/`.
2. Favicon + manifest + base.html.twig `<head>` wiring. Verify via browser tab + Lighthouse.
3. Logo component + header integration. Verify across public and authenticated layouts.
4. Hero section on `/`. Verify overlay copy doesn't collide with the illustration at all breakpoints.
5. How-it-works section. Verify visual consistency of the three-step set.
6. `EmptyState` component. Wire into the four target screens.
7. Winner celebration page. Wire the share button Stimulus controller.
8. OG meta tags + absolute-URL rendering. Test on staging with real social platforms.

Each step should land as its own pull request so design review is incremental.
