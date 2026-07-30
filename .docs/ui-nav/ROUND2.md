# Round 2 — product-owner feedback, 2026-07-30

Captured verbatim as it arrived, with the code recon and the decisions already settled. This file is
the **record**; the dispatchable specs live in `items/14…` and `BUGS.md`. Nothing here may be dropped
without the product owner saying so.

All of it is **mobile-first feedback against production** (`wtips.cz`). Production was verified
**current** at capture time (it already served item 12's „Rozložení tipů", pushed the same day), so
none of these reports is a stale build — a hypothesis worth killing early, because the stream has
been bitten by it before (`CREATE-WIZARD.md` W6).

Two reference images were pasted **inline in the conversation** and could not be written to disk (no
file arrived; `find` over the temp trees returned nothing). They are transcribed below instead, which
is what keeps the item files self-contained. If the PNGs can be supplied as file paths they should be
compressed and committed to `screenshots/` per the orchestrator protocol.

---

## Decisions settled in conversation (2026-07-30)

| # | Question | Decision |
|---|---|---|
| 1 | Boost prices — the copy quotes 15/35/50, the code has 10/20/40 | **Keep 10/20/40.** The numbers in the supplied copy were illustrative. Every amount still comes from `Credits/PricingConfig`, never a literal. |
| 2 | Do the new boost headlines replace the canonical names? | **No — panel headlines only.** „Rozložení tipů ostatních" / „Konkrétní tipy kolegů" / „Měnit tip během turnaje" stay the canonical names in the confirm dialog, the prémium toggles, `/cenik` and the credit ledger. Item 12 is not undone. |
| 3 | „Měnit tip" window — 1 h before the day's first match (documented) or before each match (the new copy) | **Per match.** *„yes the booster allows 1h before each match"*. A real domain change — see item 19. It is strictly **extending**: for the day's first match the two rules coincide, for every later match per-match is later, so no player loses a window and the extend-only `max()` composition is preserved. |
| 4 | Tip matrix — what is „odehraný", and who is „každý" | **Finished = a final result has been entered; každý = any member** of the competition (not anonymous). Scheduled **and live** matches stay behind the unlock CTA. **This is stricter than today for live matches**, whose tips are currently readable because their deadline has passed. |
| 5 | Nástěnka match filters | **Chips on desktop, dropdown on mobile.** |
| 6 | Competition detail vertical order | **Pozvat kamaráda → banner → „Tabulka soutěže" heading → 5 matches + „Načíst všechny zápasy" → žebříček → prémiové funkce.** The aside stops being an aside; one column in that order. |
| 7 | First-visit credits modal | **Once per competition per user**, showing the boost prices. Also closes `BUGS.md` **B10**. |

---

## Batch 1 — Nástěnka hráče (`/nastenka`), mobile

> text: Tvoje pozice, zápasy k tipnutí a žebříček v soutěži Lipina 26/27. (Smazat, tady bude věta: Na
> této obrazovce můžeš přepínat mezi jednotlivými soutěžemi, které hraješ, uvidíš zde zrychlené volby
> pro konkrétní soutěž)
> Pod tabulku Tvoje pozice doplnit text zobrazit celou tabulku (design tlačítka se šipkou
> pod tabulku poslední tvoje tipy přidat tlačítko Tipovat zápasy v soutěži
> Moje soutěže zrusit odkazy otevřít soutěž, a udelat celé karty na proklik
> odkaz všechny soutěže přesunout pod seznam mých soutěží
> Následující zápasy: zrušit roletku s výběrem soutěže
> Následující zápasy: Filtry dat do řádku dropdown vyber?
> Následující zápasy, karta zápasu: zrušit tlačítko tipovat a nechat pouze zadat tip, celá karta by
> měla fungovat na poklik
> Následující zápasy, karta zápasu: design viz příloha

**Recon** (`templates/portal/dashboard.html.twig`):
- The subtitle is at l. 69–72 and contains a **link** to `competition_detail`; the replacement
  sentence has none, so that link disappears — acceptable, the switcher and „Moje soutěže" both
  still reach the competition.
- `.hero-rank` („Tvoje pozice", l. 76–110) has **no** link today.
- „Poslední Tvoje tipy" (l. 115+) has a „Historie →" link to `leaderboard_member`; no tip CTA.
- „Moje soutěže" cards carry **three** links: the title and „Otevřít soutěž" both to
  `competition_detail`, plus „Zobrazit na nástěnce" to `dashboard?soutez=`. The last one must survive
  the whole-card click (item 06 assumption 1 relies on it).
- „Všechny soutěže" is in the section **header** (l. 159–162); it moves below the list.
- The soutěž roletka is l. 217–232, guarded by `my_competitions|length > 1`. Deleting it removes the
  `?zapasy=vse` **widener** — see „consequence" in item 14.
- Filters are `lb-tabs` chips with counts (l. 205–215), `flex-wrap`, so they wrap on a phone.

**The „tlačítko tipovat" report needs diagnosis, not assumption.** Item 11 already removed the
„Tipovat →" action from `Match:MatchRow`, and production is current, so there is no button by that
name on the card. What exists: the state pill („Chybí tip") is a **link** (B7), and the „MŮJ TIP" box
renders „+ Zadat tip". **Hypothesis, to be verified in a real browser at phone width: the linked pill
reads as a second tip button.** Report which explanation was true.

### The card mock (transcribed — pasted inline, no file)

Product owner: *„here is the promised match card — note the CTA is malformed, it is just to show"*.

```
┌───────────────────────────────────────────────────────────┐
│           1. KOLO   31. 7.   18:00      [ ⚠ CHYBÍ TIP ]    │
│                                                            │
│      (FRÝ)                 1 : 1                 (HRA)     │
│     DOMÁCÍ                                      Hranice    │
│  Frýdek-Míst…                                    HOSTÉ     │
│                                                            │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                  Tipovat            →                │  │
│  └──────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────┘
```

- Header row, centred: round („1. KOLO", small grey caps) · date („31. 7.") · time („18:00"), both
  bold white; the state pill is right-aligned, amber, **outlined** (not filled).
- Middle: team coin + `DOMÁCÍ`/`HOSTÉ` role label + name, with the big score centred. The two sides
  are **mirrored** — home shows role above name, away shows name above role.
- Long names ellipsize („Frýdek-Míst…"), which is B7's existing behaviour.
- Footer: a full-width CTA bar.

**Orchestrator decision, cheap to reverse.** The mock's „Tipovat →" contradicts the instruction to
*remove* „tipovat" and keep „Zadat tip"; the product owner called the CTA malformed and illustrative.
Resolution: **the whole card becomes ONE `<a>`, and the footer bar is a button-*styled* element
inside it reading „Zadat tip"** — not a nested interactive element. That satisfies „celá karta na
proklik" and „nechat pouze zadat tip" together, and it is the only reading that does not nest a
button inside a link (B7 refused whole-row linking for exactly that reason).

**Open detail:** the mock shows „1 : 1" on an *upcoming* match (31. 7. 18:00, „CHYBÍ TIP"), which
cannot both be true — presumably the mock reused a played fixture. Since the kickoff date and time
move into the header, the centre slot should show the **score when one exists** and otherwise the
`vs` separator, not a duplicated time. Flag if wrong.

---

## Batch 2 — Detail soutěže (`/souteze/{id}`)

> zde musíme dostat popis soutěže (nové pole v administraci a při zakládání)
> Nad banner tipněte všechny soutěže bude tlačítko pozvat kamaráda
> Pod ním bude napsáno tabulka souteze
> Zápasy musí fungovat na proklik
> Zobrazit pouze 5 zápasů, pod tím umístit tlačítko načíst všechny zápasy
> Pod tim bude tabulka s hodnocením a posléze s premiovými funkcemi
> Když najedu do soutěže poprvíé, chtěl bych aby vyskočil modal s tou tabulkou kreditů, s možností
> vypnout, křížkem, nebo tlačítkem, pochopil jsem, již nezobrazovat .

Order settled — decision 6 above. Notes:

- **„popis soutěže" is a genuinely new field**: a `description` column on `Competition`, a generated
  migration, the create wizard, the admin global-competition form, `competition_edit`, and the detail
  page. The only item in round 2 that touches the schema.
- **„Zobrazit pouze 5 zápasů" already exists on the Nástěnka** — the `reveal` Stimulus controller
  with `matches_visible = 5` and a „Zobrazit další (N)" button. Reuse it; do not write a second one.
  The label here is „Načíst všechny zápasy".
- **„Pozvat kamaráda"** — the existing „Pozvat" action points at
  `/souteze/{id}/nastaveni#pozvanky` (item 08). Keep that target unless told otherwise.
- **The first-visit modal** is per user per competition and shows the boost prices from
  `PricingConfig`. It closes **B10**, which is currently `BLOCKED` for exactly this gap. Dismissal
  needs stored state — the natural home is the viewer's `Membership`.

---

## Batch 3 — Detail zápasu (`/souteze/{id}/zapasy/{matchId}`)

> uložit tip
> Nevim co znamená tipy soutěže

**Recon:**
- The submit button (`Guess/GuessSubmitForm.html.twig:230–237`) reads **„Odeslat tip"** (new) /
  **„Upravit tip"** (existing) / **„Smazat tip"** (clearing an existing tip). „uložit tip" is read as
  *use „Uložit tip"* for both the new and the edit case; „Smazat tip" stays, since it does the
  opposite of saving.
- **„Tipy soutěže"** is the heading of `Guess/MatchGuessesList.html.twig:4`. It lists **other
  members' tips on this match in this competition** — which the name does not say, hence the
  confusion. Needs a name that does. Vocabulary already in use nearby: „Konkrétní tipy kolegů" (the
  boost), „Jak tipovali ostatní" and „Pořadí za zápas" (item 10's match page).

---

## Batch 4 — Boost CTA panel copy

> Tvoje vylepšení změnit na: Získej výhody
> Pod to dát tuto větu: Za kredity si můžete odemknout výhody, které vám pomohou získat lepší přehled
> a větší kontrolu při tipování v soutěži.
>
> Jak tipují ostatní?
> Odemkněte procentuální rozložení tipů 1 / X / 2 ostatních hráčů ve vaší soutěži. Konkrétní tipy
> zůstávají skryté.
> Koupit za 15 kr.
>
> Přesné tipy soupeřů
> Chcete vědět, jak tipuje váš soupeř? Odemkněte si přesné tipy ostatních hráčů ve vaší soutěži.
> Koupit za 35 kr.
>
> Počkejte si na sestavy
> Chcete si počkat na soupisky? Odemkněte si možnost upravit své tipy až 1 hodinu před začátkem
> zápasu.
> Koupit za 50 kr.

**Prices are NOT changing** (decision 1) — the CTA reads „Koupit za {{ price }} kr." with the amount
from `PricingConfig`, i.e. **10 / 20 / 40**. The quoted 15/35/50 were illustrative.

**The headlines are panel copy only** (decision 2) — `BoostType::label()` is untouched.

**The third description states a rule that is about to become true** (decision 3): „až 1 hodinu před
začátkem zápasu" is per-match, which today's domain does not do. The copy may only ship **together
with** item 19, or it will promise something the product does not honour.

---

## Batch 5 — Tabulka tipů (`/zebricek/matice`)

> at se tam klidně člověk dostane, ale ať nevidí tipy, respektive mělo by tam být vidět jen to, co je
> už odehráno zbytek na CTA odemknout -> odehrané zápasy vidí každý vždy, budoucí a probíhající jen
> pokud mám booster

Settled by decision 4. Consequences to respect:

- The page is currently gated by `leaderboard_details` (member/owner/admin). It becomes reachable by
  any **member**, with visibility decided **per match** instead of per page.
- **`leaderboard_details` must not be widened** — item 05 recorded that widening the public board must
  never widen the tip-revealing sub-pages. The per-match gate is the new mechanism, not a looser voter.
- **Live matches become hidden**, which `TipVisibilityGate` currently reveals (entitled OR past
  deadline). That is a deliberate tightening and must be written into `.docs/DOMAIN.md`.
- Managers and admins still get **no free pass** (`CompetitionEntitlements`).

---

## Batch 6 — Tabulka soutěže / Žebříček (`/zebricek?soutez=…`)

> v hlavičce zanechat pouze nadpis Žebříček, text pod nadpisem smazat
> smazat ikonky, hráčů, odehrano …
> roletka soutěže zůstává
> zůstává moje pozice (bez tlačítka tipovat)
> Posléze hned tabulka
> zrušit filtry

**Two ambiguities — do not implement until answered:**

1. **„zrušit filtry"** — the page has two different filter mechanisms (item 05): the **období tabs**
   (`?obdobi=celkem|kolo|7dni|mesic`) and the **filter bar** (`?hledat` search, `?razeni` sort,
   `?vse` expand). The product owner linked a URL carrying `&obdobi=7dni`, i.e. they were *using* the
   period tabs, so „filtry" most likely means the bar — but this is a guess and the difference is a
   whole feature.
2. **The TOP 3 podium** is not mentioned. „Posléze hned tabulka" (then immediately the table) reads as
   though the podium goes too, but it may simply not have been listed.

Everything else is unambiguous: hero keeps only the „Žebříček" heading, the sub-heading text and the
four hero stats (HRÁČŮ / ODEHRÁNO / KOLO / AKTUALIZACE) are deleted, the switcher stays, „Tvoje
pozice" stays minus its tip button, then the table.

---

## Batch 7 — display bug, PIN + odkaz (screenshot, inline)

> Chyba v zobrazení u pinu a odkazu

Filed as **B13** in `BUGS.md`.

Transcribed from the screenshot (iPhone-width, `/souteze/{id}/nastaveni#pozvanky`, „Pozvánky" block):
the **PIN** value box (`7 5 3 2 6 6 2 1`) and the **ODKAZ** box
(`https://wtips.cz/souteze/pozvanka/8dbe3879053a97550…`) both **overflow their card horizontally** —
each box runs past the card's right edge and is clipped by the viewport, the URL cut mid-string with
no ellipsis. Below each sit „Obnovit" / „Zrušit" actions, which render correctly.

---

## Batch 8 — the invite → register → verify funnel loses the join (**functional bug**)

> Poslal jsem invite link do soutěž Lipina, zaregistroval jsem se, dokonce tam bylo napsáno že po
> registraci se do skupiny připojím, posléze jsem se dostal do obrazovky, kde mě to prosilo o
> potvrzení, ale menu a záložky jsem mohl procházet, měly by zmizet… a když jsem potvrdil e-mail,
> hodilo mě to do této obrazovky [screenshot] — podle mě by to člověka mělo automaticky hodit do
> detailu soutěže

**This is the highest-severity report of round 2**: a user invited by shareable link registered, was
*promised* the join in the sign-up copy, and ended on „Zatím nehraješ v žádné soutěži". **The join
silently did not happen.** Every link-invited sign-up is losing its competition.

Screenshot (transcribed, inline only): post-verification `/nastenka`, green flash „E-mail byl úspěšně
ověřen. Jsi přihlášen(a).", heading „Ahoj, Rejdlik.", then the empty state „Zatím nehraješ v žádné
soutěži" with „Vytvořit soutěž" (primary) + „Procházet soutěže". The full nav bar (bell, +, avatar,
hamburger) is present.

**Diagnose before fixing — the documented design says this should already work.** `BUGS.md` B1's
assumptions state: *„a shareable link proves nothing, so the landing page stores the join intent and
sends the user to the airlock itself — `LoginSubscriber` completes the join after verification."* So
either that path regressed, or registration reached by an invitation link never stored the intent in
the first place. Establish which, and say so.

### Also asked in the same message

**Empty-state priority on `/nastenka`** — for a registered user in no competition:

> primárně informace ve stylu, znám pin → připojit do soutěže / Procházet soutěže / a až pak jako
> třetí a menší možnost založit si vlastní soutěž

So the order inverts: **1. PIN join (primary) · 2. Procházet soutěže · 3. Založit vlastní soutěž
(third, visually smaller)**. Today there is no PIN affordance in that empty state at all, and
„Vytvořit soutěž" is the primary button. The PIN bar partial exists
(`_partials/join_by_pin_form.html.twig`) but item 06 moved its only call site to `/souteze`.

**Stateful join intent, before any account exists:**

> when i went through link or entered pin — i want to see even before registration that i am about to
> join this competition, remember it and then go to the competition -> so clicking link or entering
> pin without account must be stateful stored in session or somewhere and during sign-in /
> registration it should show me i will join this competition

Three requirements: (1) an **anonymous** visitor following an invite link **or entering a PIN** is
told which competition they are about to join, **before** registering; (2) that intent survives
sign-up **and** sign-in; (3) afterwards the user lands on the **competition detail**, not the
Nástěnka.

Note the PIN half is new: `competition_join_by_pin` is `🔒`, so an anonymous visitor cannot enter a
PIN at all today.

## Batch 9 — the verification airlock does not confine

> Tohle je to ověření, kde bych neměl mít možnost nikam kliknout https://wtips.cz/overeni-ceka
> now i am able to click anywhere and go through

The airlock page renders the **full navigation** (visible in the batch-8 screenshot), and the product
owner reports actually getting through, not merely being bounced back.

**Two candidate causes — establish which is true, do not assume:**
1. **Cosmetic only.** The guard works and every click bounces back to `/overeni-ceka`, but because the
   nav is still rendered the app *looks* navigable. The fix is to strip the chrome.
2. **The guard is not holding.** `RequireVerifiedEmailSubscriber` was fixed in B1 (priority 8 → 7,
   deny-list → allow-list) — if pages are genuinely reachable it has regressed, or the deployed build
   differs, or a route slipped the allow-list.

Either way the airlock should render **without** the nav's primary links and CTA, as its own bare
shell (`templates/auth/_layout.html.twig` is the existing precedent for a chrome-free page).

## Batch 10 — „Uzamknout tipy" offers no date (**B2 appears not to work**)

> I want to be able to select date of the deadline and not immediately lock -> i should be able to
> choose either

Screenshot: the confirm modal titled „Uzamknout tipy", body „Tipy všech členů se okamžitě uzamknou,
jako by soutěž právě odstartovala. Odemknout je půjde jen do výkopu prvního zápasu.", buttons
„Zrušit" / „Ano, uzamknout". **No „Ihned" / „V určený čas" choice, no datepicker.**

**But this is exactly what B2 shipped** (`4e5f482`) — „the modal offers two mutually exclusive
choices: Ihned (default) / V určený čas". Production is current, so the feature exists in the
deployed code and is not reaching the screen. **Diagnose; do not rebuild B2.** The mechanism B2 used
is the `confirm` controller's optional **`fields` target**: an element inside the form that JS
*moves into the dialog* and reveals there. Prime suspects, in order:

1. The `fields` element is not being found/moved (selector or Stimulus target mismatch), so the dialog
   renders the plain message — which is precisely the no-JS fallback B2 documented („Without JS the
   form posts „Ihned" — exactly the pre-B2 behaviour"). A silent JS failure looks identical.
2. The block is conditionally rendered and its condition is false here (B2 forbids scheduling on an
   already-locked competition, and requires the moment to be before the competition start).
3. A JS error earlier on the page aborts Stimulus before the controller connects.

Read `BUGS.md` B2 „As built" and `.docs/features/confirm-modal.md` first. Report which it was.

## Batch 11 — the browser offers to save the password before it is confirmed

> Při registraci mi to hodí chybu v tom smyslu, že vyplním poprvé heslo a už se mě to ptá, jestli ho
> chci uložit, a ještě jsem jej ani nepotvrdil (neopsal podruhé)…
> probably browser issue, needs improvement probably some HTML attributes tweaking

The registration form's two password fields need the correct autofill semantics so the browser treats
them as one new-password pair rather than prompting after the first. The product owner's own reading
(„some HTML attributes tweaking") is the likely fix — `autocomplete="new-password"` on **both**
fields — but verify in a real browser rather than assuming, and check the other password-pair
surfaces (`app_reset_password`, the profile password change) for the same gap.

## Batch 12 — PIN inputs on the auth pages are useless

> on the sign in and registration pages the pin inputs are useless, remove it from there

Remove the PIN input from `/prihlaseni` and `/registrace`. Note this is **not** in tension with batch
8's „entering a PIN without an account must be stateful": that asks for the PIN to be enterable
*before* an account exists and to be **remembered through** sign-up — not for a dead PIN box to sit on
the auth forms. The two must be designed together, which is why they belong to the same agent.

## Batch 13 — credits in the header

> in the header i want to see my credits and clicking on it takes me to the credit buying page

The viewer's credit balance becomes visible in the top bar itself and links to `credits_buy`
(`/kredity/koupit`). Today `CreditBalance` renders only inside the avatar dropdown and the mobile
menu — `UI-MAP.md` §6 already lists „**Kredity is hidden** — reachable only from the avatar dropdown /
mobile menu" as a known IA pain point, so this closes it.

Watch the mobile bar, which is already crowded: brand · bell · „+" · avatar · hamburger. Adding a
sixth element needs a deliberate answer, not a squeeze — and the bar is a **sticky glass** element, so
anything added to it is on screen at all times.

## Batch 14 — the notification dropdown overflows the viewport on mobile

> malformed notifications overflow on mobile

Screenshot (inline, no file): with the bell open on a phone, the dropdown panel is anchored so that it
extends **past the LEFT edge of the viewport** — the heading „Oznámení" is clipped to „ení" — while its
right edge stops mid-screen. The panel contains „Zatím žádná oznámení." and „Zobrazit vše"; both
render, they are just positioned off-screen. Underneath it the admin sidebar and „Nákupy kreditů"
page are visible, i.e. this reproduces in the **admin shell** as well as the portal one.

Filed as **B18**. Note the shape of the bug: the panel is presumably positioned relative to the bell
(right-aligned to a trigger that sits near the middle-right of a narrow bar), so at phone width it
runs off the opposite edge. Related in kind — though not in mechanism — to **B3**, where a dropdown
was cropped by a clipping ancestor and the fix was to re-parent it to `<body>`. Do not assume the same
cause; measure it.

## Batch 15 — a much simpler public footer

> In the footer i need to remove the links (when unauthenticated) „Časté otázky", „Funkce", „Ceník",
> „Pro firmy" — make just simpler version of footer. Not „V Praze" but „Ve Frýdku-Místku", really
> simple

Target is the **`marketing`** variant of `templates/components/Layout/Footer.html.twig` — the one an
unauthenticated visitor gets on the public pages. (The `app` variant, shown to authenticated users, is
already a single mini row with only „Ochrana soukromí"; it needs no change beyond the city line if it
ever grows one.)

- Drop **Funkce · Ceník · Pro firmy** (the „Produkt" column) and **Časté otázky** („Společnost").
- What survives: the brand block, **Soutěže**, the Účet column (Přihlášení / Registrace, or
  Nástěnka / Profil), **Ochrana soukromí**, and the copyright line.
- **„Vyrobeno v Praze." → „Vyrobeno ve Frýdku-Místku."** (`Footer.html.twig:57`).
- „really simple" — the four-column grid is no longer justified by two surviving links. Collapse it;
  the `app` variant is the house precedent for what simple looks like here.

**Consequence to state, not to silently accept.** Those four routes are `noindex, nofollow` (item 01)
and the footer is currently their main entry point. After this change the only path to them is a
chain: the homepage's two „Funkce" CTAs → `/funkce` → `/cenik` → `/pro-firmy` and `/faq`. Measured:

| Route | Linked from, after this change |
|---|---|
| `app_features` | `templates/home.html.twig` only |
| `app_pricing` | `templates/public/features.html.twig` only |
| `app_for_business` | `templates/public/pricing.html.twig` only |
| `app_faq` | `templates/public/pricing.html.twig` only |

Nothing 404s, so the stream's hard constraint holds. But four de-indexed pages hanging off one
homepage CTA is a thin justification for keeping them, and **no backwards compatibility is owed —
there are no users yet.** Deleting them outright is a live option and a decision for the product
owner; do not take it as part of this change.

## Batch 16 — fabricated numbers on the sign-in page

> On sign in page there are fake numbers: 12 400+ HRÁČŮ / 340 AKTIVNÍCH SOUTĚŽÍ / 98 % DOPORUČÍ DÁL —
> Remove this completely
> On sign in `247 HRÁČŮ TIPUJE PRÁVĚ TEĎ` remove too

`templates/auth/login.html.twig:24-51` (the three stat tiles) and `:12` (the „247 hráčů tipuje právě
teď" live pill). Invented figures claiming a user base that does not exist, on the page where someone
decides to sign up. Removed completely — **not** replaced with real measured counts, which would be a
different product decision and would also read as near-zero today.

Handed to the invite-funnel agent, which already owns that template. It was also asked to sweep
`/registrace` and the invitation landings for the same pattern and to report — without touching
`templates/home.html.twig`, which is a separate surface.

## Batches 17–19 — the two list pages (specced as `items/15-simplify-list-pages.md`)

> For the filters panel in žebříček — keep only player name search, it can be outside of card, just the
> input to take less space
> On /souteze remove the filters panel too, completely whole filters/search card
> the numbers on /souteze in cards shows different numbers when i am logged in or not -> show always
> the same global numbers

The first line resolves the „zrušit filtry" ambiguity flagged in batch 6: it meant the **filter card**,
which collapses to a bare name-search input.

**Three further decisions taken 2026-07-30:**

| Question | Decision | Consequence |
|---|---|---|
| Do the Žebříček period tabs stay? | **No — remove them, all-time board only** | Retires `?obdobi=`, the `LeaderboardTimeFilter` enum and the „Poslední kolo" **leaderboard** resolution built in item 02. Round *grouping* on match lists is a different feature and stays. |
| Does the TOP 3 podium stay? | **Keep, desktop only** | Hidden on phones, where it pushes the standings below the fold. |
| What happens to `Competition:FilterBar` once `/souteze` drops it? | **Keep the component, rendered in `/_design` only** | It must be **labelled there as having no production call site** — `/_design` half A is the gallery of *shipped* components (item 13), so an unlabelled entry would imply it is in use. |

**The `/souteze` hero stats reverse item 07's assumptions 1 and 2**, which deliberately scoped those
figures to the viewer's own world so that „a visitor in nothing sees honest zeroes, not the platform's
global totals". The product owner wants one global set for everyone. The item requires the *sub-labels*
to follow — a global figure over a personal sub-label („+N tento týden" counting the viewer's own
joins) would be worse than either — and requires the numbers to stay **measured**: global totals on a
young product are small, and this round is removing invented statistics elsewhere, so padding them
would be self-defeating.
