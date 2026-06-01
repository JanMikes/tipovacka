I now have everything needed. Here is the complete frontend infrastructure catalog.

---

# Frontend Infrastructure Catalog — `/Users/janmikes/www/tipovacka`

App: Symfony 8 + AssetMapper (no Webpack/Vite), Tailwind CSS v4 via `symfonycasts/tailwind-bundle`, Hotwire Stimulus + Turbo, UX Icons (lucide). Czech-language UI. Light "navy/cyan" brand theme. There is **no dark mode** and no theme switching.

---

## 1. Tailwind v4 `@theme` tokens (verbatim)

From `/Users/janmikes/www/tipovacka/assets/styles/app.css` lines 5–27. The file begins with three imports:

```css
@import "tailwindcss";
@import "../vendor/flatpickr/dist/flatpickr.min.css";
@import "../vendor/tom-select/dist/css/tom-select.min.css";
```

`@theme` block (the entire custom design token set):

```css
@theme {
    /* Brand navy — used for primary text, dark surfaces, nav bar */
    --color-navy-50:  #eef2f9;
    --color-navy-100: #d9e2f1;
    --color-navy-200: #b6c4de;
    --color-navy-500: #23478a;
    --color-navy-700: #0f2d5f;
    --color-navy-800: #0b2552;
    --color-navy-900: #081e44;

    /* Accent cyan — CTA buttons, focus rings, highlights */
    --color-cyan-100: #d6f0fa;
    --color-cyan-400: #3eb5e6;
    --color-cyan-500: #149ad5;
    --color-cyan-600: #0f84b8;

    /* Soft shadows — cards, lifted surfaces */
    --shadow-card:       0 1px 2px rgba(8, 30, 68, 0.04), 0 2px 8px -2px rgba(8, 30, 68, 0.06);
    --shadow-card-hover: 0 10px 30px -10px rgba(8, 30, 68, 0.18);

    /* Consistent site container max-width */
    --container-site: 88rem;
}
```

Notes:
- `navy` and `cyan` **override** Tailwind's default palette names. `cyan` is redefined (so default Tailwind `cyan-*` shades not listed here, e.g. `cyan-200/700/900`, are **removed** in v4 — only `100/400/500/600` exist). `navy` is fully custom.
- Custom utilities generated: `shadow-card`, `shadow-card-hover` (used in templates and in `confirm_controller.js`), and container width token `--container-site` (88rem).
- Red palette (`--color-red-400`, `--color-red-500`) is referenced but uses Tailwind defaults (not overridden).

### Other custom CSS in `app.css` (beyond `@theme`)
All hand-written CSS is **hard-coded to the navy/cyan light look** (see §6):

| Block | Lines | Purpose |
|---|---|---|
| `@layer base` button cursor rules | 30–45 | `cursor: pointer` on enabled buttons, `not-allowed` on disabled. Theme-agnostic. |
| `@layer utilities` `[aria-invalid="true"]` | 51–60 | Red border + red focus ring on server-side-invalid inputs (Symfony sets `aria-invalid`). Uses `--color-red-400`. |
| `label.required::after` | 65–69 | Red asterisk on required-field labels (`--color-red-500`). |
| **Flatpickr skin** | 71–235 | Full reskin of the date picker to navy/cyan. Hard-codes `--color-navy-*`, `--color-cyan-*`, white text, `rgba(8,30,68,...)`. ~165 lines, the largest custom block. |
| `.confirm-dialog` + `::backdrop` | 237–285 | `<dialog>` open/close animation for the confirm modal. Backdrop `rgba(8,30,68,0.6)` + `blur(4px)`. Uses `@starting-style` and `allow-discrete` transitions. |
| `.hero-bg` + `::before`/`::after` | 287–322 | Public homepage hero background: diagonal white→`#eaf2f9` gradient, cyan/navy radial washes, masked navy dot-grid (`rgba(8,30,68,0.14)`), hairline divider. Fully light-themed. |
| **Tom Select skin** | 324–419 | Full reskin of `tom-select` dropdown/control to navy/cyan to match native inputs. Includes `select[data-controller~="tom-select"] { opacity: 0 }` to hide the native `<select>` before the JS connects. |

---

## 2. Stimulus controller inventory

All controllers live in `/Users/janmikes/www/tipovacka/assets/controllers/`. They are auto-registered by `assets/stimulus_bootstrap.js` (`startStimulusApp()` from `@symfony/stimulus-bundle`, which auto-discovers `*_controller.js` and `controllers.json`). Filename → controller name (e.g. `mobile_nav_controller.js` → `mobile-nav`).

| Controller | File | Purpose | Targets | Values | Actions | Used in (templates) |
|---|---|---|---|---|---|---|
| **confirm** | `confirm_controller.js` | Intercepts form `submit`, shows a styled `<dialog>` confirm modal (replaces `window.confirm`) for destructive actions. Builds the dialog in JS. `danger`/`warning` variants. | — (builds DOM imperatively) | `message:String`, `title` (def "Potvrdit akci"), `confirmLabel` (def "Ano, pokračovat"), `cancelLabel` (def "Zrušit"), `variant` (def "danger") | binds `submit` listener in `connect()` | **18 usages across 6 files** (most-used controller) — delete/remove/revoke flows |
| **confirm-recalculation** | `confirm_recalculation_controller.js` | On rule-config form submit, if `count > 0` shows `window.confirm("…přepočítá body všech…")`; else submits silently. | — | `count:Number` | `submit->confirm-recalculation#confirmIfNeeded` | `templates/portal/tournament/rule_configuration.html.twig`, `templates/admin/tournament/rule_configuration.html.twig` (wired via Twig form `attr` array, so it didn't match a `data-controller="…"` grep) |
| **datepicker** | `datepicker_controller.js` | Wraps a field in **flatpickr** (locale `Czech`, 24h, `disableMobile`, `altInput`). Modes: `date` / `datetime` / `time`. Injects a custom SVG clear button, hides/shows it on value change. Carefully avoids Stimulus unmatch/match loops by wrapping the `altInput`. | — | `mode` (def "datetime"), `minDate:String`, `maxDate:String` | onReady/onChange hooks internal | `templates/form/_form_theme.html.twig`, `templates/portal/sport_match/detail.html.twig` |
| **hello** | `hello_controller.js` | **Symfony scaffold demo** — sets element text to "Hello Stimulus!…". | — | — | — | **1 usage in templates but it is the demo placeholder; safe to delete** (Symfony's stock example) |
| **mobile-nav** | `mobile_nav_controller.js` | Toggles the mobile hamburger menu: show/hide menu, swap open/close icons, set `aria-expanded`. | `menu`, `button`, `iconOpen`, `iconClose` | — | `toggle` | `templates/base.html.twig` (the only one) |
| **orderable-list** | `orderable_list_controller.js` | Native HTML5 drag-and-drop reordering of `<li>` items; after each drop, rebuilds hidden inputs `resolve_ties_form[orderedUserIds][N]` to reflect order. | `list`, `item`, `hiddenContainer` | — | dragstart/dragover/drop/dragend (attached in JS) | `templates/portal/leaderboard/resolve_ties.html.twig` (wired via Twig form `attr`, so missed by `data-controller="…"` grep) |
| **password-visibility** | `password_visibility_controller.js` | Toggles `<input type=password/text>`, swaps eye / eye-off icons. | `input`, `iconShow`, `iconHide` | — | `toggle` | **4 files** (login + password forms) |
| **reveal** | `reveal_controller.js` | "Show more / show less" — hides list items beyond `visible` count, toggles the rest; updates button label with hidden count. | `item`, `toggle` | `visible:Number` (def 5), `moreLabel` (def "Zobrazit další"), `lessLabel` (def "Zobrazit méně") | `reveal#toggle` | `templates/portal/dashboard.html.twig` (3 `data-controller="reveal"` instances in 1 file) |
| **tom-select** | `tom_select_controller.js` | Enhances a `<select>` with **Tom Select** (searchable, custom option/item renderers showing nickname + fullName subtitle + "neověřený" badge). Optional `submitOnChange`. | — | `placeholder` (def ""), `submitOnChange:Boolean` (def false), `noResultsText` (def "Nic nenalezeno") | onChange internal | `templates/portal/group/manage_member_tips.html.twig` (only one) |

**Additional registered controllers (from `controllers.json`, third-party UX bundles):**
- `@symfony/ux-live-component` → `live` (enabled, `fetch: lazy`, auto-imports `live.min.css`).
- `@symfony/ux-turbo` → `turbo-core` (enabled, eager) and `mercure-turbo-stream` (**disabled**).

**Dead/scaffold controllers:** `hello` (Symfony demo).

---

## 3. JS/CSS vendor dependency inventory + actual usage

Declared in `/Users/janmikes/www/tipovacka/importmap.php`. Downloaded copies live under `assets/vendor/` (tracked by `assets/vendor/installed.php`).

| Library | importmap version | Declared in `importmap.php`? | Actually imported / used? | Where |
|---|---|---|---|---|
| `@hotwired/stimulus` | 3.2.2 | Yes | **Yes** | every controller |
| `@symfony/stimulus-bundle` | (path) | Yes | **Yes** | `stimulus_bootstrap.js` |
| `@hotwired/turbo` | 7.3.0 | Yes | **Yes** (via `@symfony/ux-turbo` turbo-core) | globally registered, but **Turbo is disabled** via `data-turbo="false"` on `<body>` (`base.html.twig:40`); **no template opts back in** (`grep data-turbo="true"` → 0 hits) |
| `@symfony/ux-live-component` | (path) | Yes | **Yes** (registered via controllers.json) | live components |
| `flatpickr` + `flatpickr/dist/flatpickr.min.css` + `flatpickr/dist/l10n/cs.js` | 4.6.13 | Yes | **Yes** | `datepicker_controller.js`; CSS imported in `app.css` |
| `tom-select` + CSS + `@orchidjs/sifter` + `@orchidjs/unicode-variants` | 2.6.0 / 1.1.0 / 1.1.2 | Yes | **Yes** | `tom_select_controller.js`; CSS imported in `app.css` |
| `alpinejs` | 3.15.3 | Yes | **Imported & `Alpine.start()`d in `app.js`, but NO Alpine directives exist anywhere** — `grep x-data\|x-show\|@click\|x-init\|Alpine` in templates → 0 hits. **Effectively unused payload.** | `assets/app.js` only |
| `leaflet` + `leaflet/dist/leaflet.min.css` | 1.9.4 | **Yes (in importmap.php)** | **UNUSED** — no `leaflet`/`L.`/map reference in `templates/`, `src/`, or any controller. Vendor files + 3 marker PNGs present but orphaned. | nowhere |
| `glightbox/dist/css/glightbox.min.css` | 3.3.1 | **Yes (CSS only in importmap.php)** | **UNUSED** — no `GLightbox`/`data-gallery`/`glightbox` reference in templates or JS. Vendored but never imported. | nowhere |

**Orphaned in `assets/vendor/` but NOT in `importmap.php`** (downloaded at some point, never wired up — `grep konva|chart.js|signature_pad importmap.php` → 0):
- `assets/vendor/konva/` — **UNUSED** (no `Konva` reference anywhere).
- `assets/vendor/chart.js/` — **UNUSED** (no `Chart`/`chart.js` reference anywhere).
- `assets/vendor/signature_pad/` — **UNUSED** (no `SignaturePad` reference anywhere).

**Summary of dead frontend dependencies (candidates for removal):** `alpinejs` (imported but no directives), `leaflet` (+CSS +marker images), `glightbox` (CSS), and the orphaned vendor dirs `konva`, `chart.js`, `signature_pad`. The `hello` Stimulus controller is also dead scaffold.

---

## 4. Icon system (UX Icons + lucide)

- Bundle: `symfony/ux-icons` `^3.0` (composer).
- Config `/Users/janmikes/www/tipovacka/config/packages/ux_icons.yaml`:
  - `icon_dir: %kernel.project_dir%/assets/icons`
  - `default_icon_attributes: { height: '1em', width: '1em' }`
  - `iconify: { enabled: true, on_demand: false }` — icons are **NOT fetched on demand**; the SVG must exist locally.
  - `ignore_not_found: false` in dev (**missing icon throws at render time**), `true` in prod.
- Local lucide set: **47 SVGs** in `/Users/janmikes/www/tipovacka/assets/icons/lucide/`. Referenced in templates as `<twig:ux:icon name="lucide:<name>" />`.

**Icon inventory issues found:**
- **Missing icon (will throw in dev):** `lucide:flag` is referenced at `templates/invitation/landing.html.twig:64` but **`assets/icons/lucide/flag.svg` does not exist**. Per `CLAUDE.md` and `ignore_not_found: false`, this throws a render-time exception in dev (prod silently ignores). Needs `bin/console ux:icons:import lucide:flag`.
- **Unused local icons (present but never referenced):** `chevron-left.svg`, `medal.svg`, `star.svg`.
- **Dynamic icon names** (icon string supplied by Twig data, not literal) — these feed `ux:icon name="{{ ... }}"`:
  - `base.html.twig:177` flash messages → `cfg.icon` from a map of `circle-check-big` / `circle-alert` / `triangle-alert` / `info` (all present).
  - `home.html.twig:48` & `:124` → `b.icon` / `f.icon`: `shield-check`, `users`, `list-ordered`, `clock`, `share-2` (all present).
  - `admin/layout.html.twig:36` → nav `item.icon`: `trophy`, `users`, `user`, `activity` (all present).
- Most-referenced icons: `user` (20), `arrow-right` (18), `users` (15), `lock` (15), `trophy` (14), `calendar` (11), `mail` (10).

---

## 5. Build / asset pipeline summary

**No Node/npm build step.** Pipeline is Symfony AssetMapper + Tailwind binary.

- **AssetMapper** (`config/packages/asset_mapper.php`): `paths: ['assets/']`, `missing_import_mode: strict`. Serves `assets/` with versioned URLs; uses the importmap.
- **importmap** (`importmap.php`): single entrypoint `app` → `./assets/app.js` (`entrypoint: true`). Rendered in `base.html.twig:37` via `{{ importmap('app') }}`. `assets/app.js` imports `./stimulus_bootstrap.js` and `alpinejs`, then `Alpine.start()`.
- **Stimulus bootstrap** (`assets/stimulus_bootstrap.js`): `startStimulusApp()` auto-loads all `assets/controllers/*_controller.js` plus the UX-bundle controllers from `assets/controllers.json`.
- **Tailwind CSS** (`symfonycasts/tailwind-bundle` `^0.12`):
  - `config/packages/symfonycasts_tailwind.php`: `binary_version: 'v4.1.11'` (standalone Tailwind v4 CLI binary, no PostCSS/Node).
  - Source: `assets/styles/app.css` (`@import "tailwindcss"` + custom). Compiled by `php bin/console tailwind:build` (composer scripts `tailwind:build` / `tailwind:watch` at lines 105–106).
  - **No `tailwind.config.js` exists** — v4 config is entirely in-CSS via `@theme`. Content scanning is automatic (Tailwind v4 auto-detects template sources).
  - Compiled output is referenced in `base.html.twig:33` as `<link rel="stylesheet" href="{{ asset('styles/app.css') }}">` (the bundle compiles in place / through AssetMapper).
- **`<body>`** (`base.html.twig:40`): `data-turbo="false"` (Turbo globally off), default body class `bg-navy-50/40 text-navy-900 antialiased`.
- **Static assets** referenced via `asset()`: favicons (`favicon-96x96.png`, `favicon.svg`, `favicon.ico`, `apple-touch-icon.png`), `site.webmanifest`, `og-default.png`, and `images/logo/logo-icon.png`.

---

## Images inventory (`assets/images/`)

8 PNGs in 4 folders:
- `hero/hero-desktop.png` — homepage hero illustration.
- `how-it-works/step-1-create-group.png`, `step-2-predict.png`, `step-3-climb.png` — landing "how it works" steps.
- `logo/logo-icon.png` — site logo (used in `base.html.twig:50` and `:194`).
- `winner/winner-personal.png`, `winner-square.png`, `winner-wide.png` — winner share/OG-style images.

(Favicons, `og-default.png`, `apple-touch-icon.png`, `site.webmanifest` are referenced via `asset()` but live in `public/`, not `assets/images/`.)

---

## 6. Coupling to the light navy/cyan theme

This is the key assessment for a re-theme. The infra splits cleanly into **theme-coupled** vs **theme-agnostic** pieces.

### Tightly coupled to the current light navy/cyan look (would need rework to re-theme)

1. **`@theme` token block** (`app.css:5–27`) — the single source of truth for `navy-*` and `cyan-*`. Re-theming starts here. Note `cyan` overrides the built-in palette, so changing it ripples through every `cyan-*` utility in templates.
2. **Flatpickr skin** (`app.css:71–235`, ~165 lines) — hard-codes navy headers, white text, cyan day-hover/selected, `rgba(8,30,68,…)` shadows. **Not using theme tokens consistently** for the light assumption (white text on navy header, light weekday bar). A dark theme would invert most of this by hand.
3. **Tom Select skin** (`app.css:324–419`) — assumes white control background (`background:#fff`), navy borders, light dropdown. Hard-coded `#fff` and `rgba(8,30,68,…)`. Would need rework for dark surfaces.
4. **`.hero-bg`** (`app.css:287–322`) — explicitly light: `linear-gradient(160deg, #ffffff 0%, #eaf2f9 100%)`, navy dot-grid on light, cyan radial wash. Purely a light-mode homepage decoration.
5. **`.confirm-dialog::backdrop`** (`app.css:265–285`) — backdrop `rgba(8,30,68,0.6)` (navy) + blur. The dialog itself is built in JS as `bg-white … ring-navy-900/5` (see below).
6. **`confirm_controller.js`** — imperatively builds the modal with **hard-coded light classes**: `bg-white shadow-card ring-1 ring-navy-900/5`, `text-navy-900`, `bg-navy-50 hover:bg-navy-100`, `bg-red-600/yellow-500` action buttons, `text-navy-900/70` body text. The destructive-action UI is baked light in JS, not driven by tokens.
7. **`datepicker_controller.js`** clear button — hard-codes `text-navy-900/40 hover:text-navy-900 focus:ring-cyan-500`.
8. **`tom_select_controller.js`** option renderer — hard-codes `text-navy-900/60`, `text-gray-400` ("neověřený" badge).
9. **`base.html.twig` body default** — `bg-navy-50/40 text-navy-900`; nav, flash configs (`bg-green-50/border-green-400/text-green-800` etc.) assume light surfaces. (Templates are out of scope of this file-set, but the infra defaults are set here.)
10. **`label.required::after`** and **`[aria-invalid]`** utilities use red (`--color-red-400/500`) — these work in light; in dark the red shades might need adjusting but are not strictly light-locked.

### Theme-agnostic / reusable as-is (no light assumptions)

- **AssetMapper + importmap + Tailwind binary pipeline** — entirely structural; theme lives only in `app.css`.
- **`@layer base` cursor rules** (`app.css:30–45`) — pure interaction state, no color.
- **Stimulus behavior controllers** (logic only, no styling): `mobile-nav`, `reveal`, `orderable-list`, `password-visibility`, `confirm-recalculation`. These manipulate `hidden`/`aria-*`/DOM order and **carry no color classes** — fully reusable under any theme.
- **`tom-select` / `datepicker` controllers' core logic** (TomSelect/flatpickr wiring, modes, locale, submit-on-change) is theme-agnostic; only the small inline class strings noted above are light-coupled.
- **UX Icons system** — icons are monochrome SVGs colored via `currentColor` + Tailwind text-color classes at call sites; the icon set itself is theme-neutral. Re-theming is just changing the `text-*` classes around them.
- **`.confirm-dialog` open/close animation** (transform/opacity/`@starting-style`) — the *motion* is theme-agnostic; only the backdrop color is navy.
- **`@theme` shadow tokens & container** — `--shadow-card*` use `rgba(8,30,68,…)` (navy-tinted) so they're mildly light-tuned, but `--container-site: 88rem` is purely structural.

### Re-theme effort estimate (where the work concentrates)
The light-theme coupling is concentrated in **(a) the `@theme` tokens**, **(b) two large hand-written vendor skins in `app.css`** (flatpickr ~165 lines, tom-select ~95 lines), **(c) the hero-bg block**, and **(d) hard-coded class strings inside `confirm_controller.js`, `datepicker_controller.js`, `tom_select_controller.js`**. Everything else (pipeline, behavior controllers, icons, importmap) is reusable. A clean re-theme would also benefit from first removing the dead deps (`alpinejs`, `leaflet`, `glightbox`, `konva`, `chart.js`, `signature_pad`, `hello` controller) and fixing the missing `lucide:flag` icon.