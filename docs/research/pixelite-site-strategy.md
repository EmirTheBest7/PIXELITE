# Pixelite.cz — Site Architecture, Page Specs and Growth Roadmap

*Companion to [`competitor-research.md`](competitor-research.md) (evidence) and [`inventory/`](inventory/) (URL appendices). Snapshot: 2 Oct 2026. **Proposal only — no code has been changed and nothing here is approved until reviewed.***

**Guiding rule:** a 20-page Pixelite site with excellent content beats a 100-page site of thin content. Every page below must have a unique purpose, a unique audience question, and content only the owner can supply truthfully. Anything that would need invented facts is placed in "do not build yet".

**Hard constraints carried over:** existing visual design stays (tokens and components in `public/assets/style.css`); no invented clients, team, statistics, awards, years, addresses or prices; legal placeholders stay visible; no new trackers; plain PHP 8.3, no framework, no new dependencies; every string in both `lang/en.php` and `lang/cs.php`.

---

## A. Owner decisions that gate everything (answer before build)

| # | Decision | Why it matters | Blocks |
|---|---|---|---|
| 1 | Set `PRICE_VAT_MODE` to the true VAT status | Prices show a placeholder until set; `/pricing` repeats it | Launch, `/pricing` |
| 2 | Complete `COMPANY_*`, retention, providers, ADR/complaint text | Legal placeholders are visible on a public site | Launch |
| 3 | What is **in scope** per package, in units a customer can count (pages/sections, languages, revisions, CMS or not, copywriting, photos, SEO basics, hosting) | The research shows Webovky48 competes on counted scope; Basic (from 32,900 Kč) sits above its ≤10-page package (28,900 Kč). Scope clarity is the lever, not price. | `/pricing`, service pages |
| 4 | What is **not** included / billed separately | Honest differentiator | `/pricing` |
| 5 | Is there a maintenance/hosting offer? If so, what exactly and at what price | A page about it needs a defined product | Maintenance page |
| 6 | Realistic delivery ranges (weeks) per package | Process page; no speed promise unless honoured | `/process` |
| 7 | Who is the person: name, photo (optional), background, what they will say publicly | `/about` is credible only with a real identity | `/about` |
| 8 | Real physical presence: Karlovy Vary registered office; is there a working presence in Prague or elsewhere? | Local-SEO guard — no city pages without it | any local page |
| 9 | Permission to publish at least 2–3 real projects (own or client), with written consent | Case-study system | Portfolio go-live |
| 10 | Dreamers Ad Credits: issuer, conditions | Strip links to `/terms#dreamers` placeholders | Pricing page claims |
| 11 | Do you want a CS-first or EN-first voice? (Czech SMEs are the core buyer) | Copy review workload | all copy |

---

## B. Recommended sitemap

Legend: **Now** = Phase 1–2, **Next** = Phase 3, **Later** = Phase 4–5 (conditional), **No** = do not build.
All public pages exist as `/{lang}/…` (`en`, `cs`).

### B.1 Core pages

| Page | Route | Tier | Notes |
|---|---|---|---|
| Home | `/{lang}/` | exists → improve | additions in §E |
| Order / project request | `/{lang}/order` | exists | keep single pipeline |
| Contact | `/{lang}/contact` | exists | |
| **Pricing** | `/{lang}/pricing` | **Now** | §D.1 |
| **How it works** | `/{lang}/process` | **Now** | §D.2 |
| **About** | `/{lang}/about` | **Now** (needs owner decision 7) | §D.3 |
| Template website | `/{lang}/website-from-template`  | Next | §D.4 |
| Custom website | `/{lang}/custom-website`  | Next | §D.4 |
| Basic website | covered on `/pricing` + home; own page only if it has distinct content | Later | avoid a near-duplicate of the other two |

> **URL language.** The current site keeps the same slugs under `/en` and `/cs`. Localised slugs help Czech SEO but cost routing and hreflang complexity. Decision: use **one English slug set** for both languages for now (simple, tested), and decide on localised Czech slugs once the page set is stable (cost: router map + sitemap + language-switch mapping + redirects).

### B.2 Commercial pages (conditional on real content)

| Page | Tier | Condition |
|---|---|---|
| Website redesign (`/redesign`) | Next/Later | Owner can describe a real redesign process and has at least one honest example or a clear scope; otherwise a section on the custom page |
| Maintenance & hosting | Later | Defined offer + price (decision 5) |
| Services hub (`/services`) | Later | ≥4 distinct, real service pages |
| E-shop / web-app pages | **No** (for now) | No proof; Pixelite's public offer is websites. Mention on `/order` as project types only |
| AI / automation pages | **No** | Same |

### B.3 Trust pages

| Page | Tier | Notes |
|---|---|---|
| About (person, values, tools) | Now | |
| Portfolio index + case studies | Next (system) / Later (publish) | Keep noindex and unlinked until ≥2–3 real, consented projects |
| Testimonials | **No** until real written quotes with permission | Never synthesised |
| Client logos | **No** until permission | |
| Reviews/ratings (Google, Clutch…) | **No** until a real profile exists with real reviews | |
| Security/privacy statement | Fold into `/about` and `/privacy` | One honest paragraph, only claims that are true and measurable |

### B.4 Content pages (only if maintainable)

| Page | Tier | Notes |
|---|---|---|
| 4–6 evergreen guides (e.g. how much a website costs in CZ; what to prepare before ordering; domain/hosting ownership; launch checklist) | Later | Guides, not a dated blog. Each must be unique, original and updated with a "reviewed on" date |
| Blog | **No** | 100+ articles is the studios' approach; a one-person studio cannot maintain it without thin output |
| Glossary | **No** | thin-content risk |
| FAQ standalone | Later | Start as a section on `/pricing` and `/process`; standalone when ≥12 real questions exist |

### B.5 Legal and utility

Privacy, Cookies, Terms (exist). Order-sent (exists, noindex). 404/500 (exist). Add: **Accessibility statement** (Later; only after an actual audit — claims must be true), **Complaint/withdrawal** text lives in Terms (owner placeholders).

### B.6 Do not build

Careers · team page (there is no team) · press/news · events/webinars · ebooks/lead magnets · customer portal/login · industry pages (barbers, lawyers…) · city/region pages · "AI for X" pages · comparison pages naming competitors · awards/partners pages · programmatic anything.

**Why programmatic local/industry pages are excluded.** The one competitor doing it at scale (Webovky48: 20 city, 14 region, 34 industry pages) shows 54–88% shared text between sibling pages, with 81 of 85 pages unreachable from the main navigation. That pattern is the textbook definition of doorway pages and is fragile against search-quality updates. It also needs a genuine local presence for city pages, which Pixelite has not established (decision 8).

---

## C. Pixelite's current architecture (summary)

*(Verified against the repo; full detail in `CLAUDE.md`.)*

- Routes: home, order (+sent), contact, privacy, cookies, terms, hidden portfolio, robots, sitemap, admin.
- Home sections: hero (CSS laptop) · 3 package cards · Dreamers credits strip · consultation · CTA/contact · violet band.
- Strengths: transparent starting prices, strict privacy posture, bilingual parity enforced by tests, dark mode, accessible consent, snapshotting of order prices, good test coverage.
- Weak points for conversion and search: only one indexable commercial page (home), no detail behind the prices, no explanation of process or person, no FAQ, no evidence of work (by design).

---

## D. Page specifications

For each: route · purpose · audience · content blocks · CTA · SEO intent · reusable components · difficulty (S/M/L). Difficulty counts template + both languages + tests.

### D.1 `/pricing` — **Now** (M)

- **Purpose.** Turn the three "from" prices into a decision: what each tier is, what it is not, what moves the price.
- **Audience.** Small-business owner or sole trader comparing options; shopper who clicked "see prices".
- **Content (all counted/real, owner-supplied):**
  1. Short intro: starting prices in CZK, VAT line from `PRICE_VAT_MODE` (placeholder remains if unset).
  2. Three package cards (reuse `.price-box`) with link to `/order?package=…`.
  3. **Comparison table** (rows = counted scope units: pages/sections, languages, CMS/editing, copy help, SEO basics, revisions, hosting setup, handover, support period). Cells only from owner decision 3.
  4. **Template vs custom explained** in plain language (one short block).
  5. **Not included / billed separately** list.
  6. **What changes the price** (3–6 honest factors).
  7. Advertising credit strip (existing component) with link to terms.
  8. FAQ (5–8 real questions, `<details>`).
  9. CTA: choose a package, or non-binding consultation.
- **CTA.** Primary: start project with package; secondary: consultation.
- **SEO intent.** "kolik stojí web", "cena webových stránek", "tvorba webu cena", "web na míru cena"; EN: "website price Czech Republic". *Volumes unverified; check Search Console/Keyword Planner before committing keyword choices.*
- **Reuse.** `.price-box`, new comparison table, new FAQ/details, existing credits strip, existing CTA band.
- **Rules.** Prices come from the same `services.items` data (no price text in templates — the existing test must keep passing). No "limited offer", no countdowns, no discounts that aren't real. Structured data: `FAQPage` only if the FAQ is visible and real; `Offer` markup only with true prices and VAT mode.

### D.2 `/process` — **Now** (S–M)

- **Purpose.** Reduce fear of the unknown: what happens from enquiry to launch to afterwards.
- **Audience.** Hesitant first-time buyers.
- **Content.** 5–6 numbered steps (enquiry → consultation → proposal/quote → build with checkpoints → review/revisions → launch & handover), what the customer needs to provide, who owns domain/hosting/content at the end, how revisions work, typical durations as **ranges the owner will honour**, what happens if the project changes. Short FAQ.
- **CTA.** Start a project / non-binding consultation.
- **SEO intent.** "jak probíhá tvorba webu", "postup tvorby webových stránek" — informational, mid-funnel.
- **Reuse.** New **steps timeline** component (numbered, mono micro-label), FAQ/details, CTA band.
- **Rules.** No guarantees of speed unless contractual; no invented stages the owner doesn't follow.

### D.3 `/about` — **Now, but only with owner-supplied content** (S)

- **Purpose.** Put a human and a way of working behind the site; the solo-builder advantage made explicit.
- **Audience.** Anyone about to send a brief and wondering "who am I writing to?".
- **Content.** Name, photo (optional but recommended), a short factual bio (only things the owner confirms: role, tools, languages spoken, where based), how projects are run (direct contact, who does what, what is subcontracted — if anything — stated honestly), working principles (clean code, accessibility, privacy, clear scope), tools/stack summary, the legal entity block (existing footer data), CTA.
- **SEO intent.** Brand and trust ("who is Pixelite"); not a keyword page.
- **Reuse.** New **founder block** (portrait + text), existing contact-details partial, CTA band.
- **Rules.** No team plural, no "years of experience", no client names, no awards. If the owner cannot supply real content, do not publish the page.

### D.4 Service pages (template + two instances) — **Next** (M for the template, S each instance)

Pages: **Template website** and **Custom website** (Basic is described through `/pricing` until it has differentiating content).

- **Purpose.** Capture search intent for the specific thing ("web ze šablony", "web na míru") and let the buyer self-qualify.
- **Standard blocks (see §G template):** summary + from-price + CTA; who it's for / who it's not for; what you get (counted); what you don't get; how it's built (process excerpt); example sections/layouts (only real or clearly labelled illustrations); price factors; FAQ; related packages; CTA.
- **SEO intent.** "tvorba webu ze šablony", "levný web pro živnostníky", "web na míru"; EN equivalents.
- **Reuse.** Pricing data, steps component, comparison table, FAQ, CTA.
- **Rules.** Each page needs ≥600 words of genuinely distinct content (not a rewording of the other). If not, fold into `/pricing`.

### D.5 Case-study system — **Next (build hidden), Later (publish)** (L)

See §F. Index `/portfolio` and detail `/portfolio/{slug}`.

### D.6 Redesign page — **Next/Later** (M)

- **Purpose.** Capture "redesign webu" / "předělání webových stránek" intent.
- **Content.** When a redesign makes sense, what is kept (domain, content, SEO rankings via redirects), audit-first approach, how URLs are preserved, risks, price relation to Basic/Custom, CTA.
- **Condition.** Owner has a defined redesign procedure; ideally one real before/after (with consent).

### D.7 Maintenance — **Later** (S once defined)

Only after decision 5. Content: exactly what is covered, response expectations stated honestly, price, how to cancel, what is not covered.

### D.8 Guides — **Later** (M each)

Fixed list, each unique and evergreen, owner-reviewed twice a year: *website cost in Czechia*, *what to prepare before ordering a website*, *domain & hosting ownership*, *launch checklist*. Each has author, "last reviewed" date, and a CTA. No more than 6 until traffic and capacity justify more.

---

## E. Home page improvements (additions, not a redesign)

1. **"How it works" strip** (3 steps) linking to `/process`.
2. **"Compare packages" link** under the cards to `/pricing`.
3. **Honest differentiators row** (3–4 short statements, only true ones): public prices · work with the builder directly · no tracking cookies (kept true by the consent registry) · bilingual site/support (if true).
4. **FAQ preview** (3–4 questions, link to full FAQ on `/pricing`/`/process`).
5. **Founder snippet** (one line + photo) linking to `/about` — only once `/about` exists.
6. **Projects teaser** — absent until real projects exist. Do **not** add a placeholder gallery.
7. Keep the existing hero, cards, credits strip, consultation and CTA band.

Not recommended: logo walls, "100+ projects" counters, rating badges, marquee tickers of keywords, scroll-hijacking heroes.

---

## F. Case-study architecture (build once, publish when real)

**Data model** (`config/portfolio.php` extended; later optionally SQLite if editing from admin is wanted — start with config files, version-controlled):

```
slug, status (draft|published), published_at, updated_at,
client_display (name, or an anonymised label) + client_consent (bool, required in ALL cases; anonymised studies still need a real project and recorded consent, and count toward the publish threshold only if verifiable),
project_type, package (template|basic|custom|none),
period (month/year or null), url (live site or null),
title{en,cs}, summary{en,cs}, problem{en,cs}, solution{en,cs}, outcome{en,cs} (qualitative, or measured with source+date),
scope[] (what was delivered), stack[] (tags),
images[] (src, alt{en,cs}, width, height, kind: desktop|mobile|detail),
quote{en,cs,attribution,permission} (optional),
metrics[] (label{en,cs}, value, source, measured_on) (optional)
```

**Rules.** A section renders only if its data exists. Missing data never produces a placeholder card in public. Sitemap lists only `published`. `/portfolio` stays `noindex`, unlinked, and absent from sitemap until ≥2–3 published studies. Metrics require a source and date; no estimates. Images carry real `alt` text and fixed dimensions (no layout shift). Detail pages get their own title/description/OG image and JSON-LD `CreativeWork` only when true.

**Page template (reading order).** Summary card (type, period, stack tags, link) → problem → what was built → result/outcome → (optional) quote → (optional) metrics → CTA. Target 400–700 words — long enough to be useful, short enough for one person to write.

**Testing.** Fixture-based tests for each optional section, for locale fallback, and for sitemap exclusion of drafts.

---

## G. Service-page template (reusable)

`templates/pages/service.php` driven by a config array per service (`config/services.php`): slug, package id, localized title/lede/audience/benefits/not-included/process-excerpt/FAQ, related slugs. Shared partials: summary header, price block (from pricing data), audience split (for / not for), counted deliverables list, steps (from `/process` data), FAQ (`<details>`), related-services row, CTA. Blocks omit themselves when data is empty; a test asserts every service has distinct text (no copied paragraphs between services) to prevent thin or duplicate pages.

---

## H. Design-system extensions (additive; same tokens)

All built from existing variables (`--c-*`, semantic `--surface/--text/--border/...`), dark-mode safe, CSP-safe (no inline styles/scripts).

| Component | Purpose | Notes |
|---|---|---|
| Inner-page hero (`.page-hero`) | Titles for pricing/process/about without the landing hero | Smaller H1 (hero scale at ≤30px per tokens), same spacing rhythm |
| Comparison table (`.compare`) | Package matrix | Horizontal scroll container with sticky first column on narrow screens; real `<table>` semantics |
| Steps timeline (`.steps`) | Process | CSS counters, no JS; vertical on mobile |
| FAQ (`<details>` accordion, `.faq`) | Questions | Native element, keyboard accessible, no JS |
| Included / not-included list (`.checklist`, `.checklist--no`) | Scope | Icon + text (not colour alone) |
| Founder block (`.person`) | About, home snippet | Portrait with fixed aspect ratio, optional |
| Micro-label (`.eyebrow`) | Small uppercase tags | Built in Lato from existing tokens; a monospace face is a new typographic device and needs explicit owner approval |
| Stat/fact row (`.facts`) | Only with verifiable facts (e.g. "From 2,999 Kč") | No invented numbers |
| Callout (`.note`) | Disclaimers (VAT, starting price) | |
| Related-links row | Cross-links between pages | |

Do not add: marquee tickers, 3D scenes beyond the existing laptop, scroll-driven pinned sections, video backgrounds, parallax. (Competitors use them; Pixelite's calm, fast, readable system is a differentiator, and they cost performance and accessibility.)

---

## I. SEO and content map

| Intent cluster | Example queries (verify volumes first) | Page | Tier |
|---|---|---|---|
| Brand | pixelite, pixelite.cz | Home, About | exists/Now |
| Price | kolik stojí web, cena webových stránek, web cena | `/pricing` | Now |
| Process | jak probíhá tvorba webu | `/process` | Now |
| Template | web ze šablony, levný web | Template page | Next |
| Custom | web na míru | Custom page | Next |
| Redesign | redesign webu, předělání webu | Redesign page | Next/Later |
| Small business | web pro živnostníky, web pro malou firmu | Home/pricing copy (not separate pages) | Now |
| Guides | domény a hosting; co připravit před objednávkou | Guides | Later |
| Local | tvorba webu Karlovy Vary | **Only** if owner confirms real presence; one honest page at most | Later/No |

**Technical SEO (continuing what exists).** Canonical + hreflang per language, sitemap with alternates, noindex for order-sent/admin/portfolio-until-real, unique `<title>`/description per page (all six competitors' homepages vary widely; Scalery's identical titles show the cost of neglecting this), `FAQPage`/`Organization`/`WebSite` structured data only where true, Open Graph image (replace current hero.png with a proper 1200×630 asset once available), internal links from home and footer to every indexable commercial page (Webovky48's 80 "orphaned" pages show the opposite: discoverable only through the sitemap).

**Measurement without trackers.** Use Google Search Console and server logs (aggregated) for visibility; no analytics script is required. If analytics is ever added, it goes through the consent registry and bumps `version`.

**Performance and accessibility claims.** Only publish claims after measuring (Lighthouse/CrUX or the existing Chrome suite) and keep the evidence in the repo; state "measured on <date>". Do not write "fast website" copy without numbers.

---

## J. Bilingual architecture

- Czech is the primary market; **write Czech first, then adapt English** (not literal translation). Native Czech review for every new page (already flagged in `CLAUDE.md`).
- Keep identical key sets (tests enforce). Prefer structured arrays for repeated blocks (steps, FAQ, table rows) so counts match across languages.
- Keep hreflang pairs complete; language switch maps to the same page.
- Currency CZK in both languages; Czech uses non-breaking spaces in prices; dates localised.
- Decide on localised slugs once (see B.1) — avoid changing later.
- EN is for foreign-based Czech-market buyers and expats; do not create EN-only content that has no Czech equivalent unless justified.

---

## K. Responsive and QA plan for each new page

- Widths: 320, 375, 390, 430, 768, 820, 1024, 1280, 1440, 1920 × EN/CS × light/dark. (The Chrome suite already has 820 and 412; add 430.)
- Assertions: no horizontal overflow, no overlap, tap targets ≥44 px, focus order and visible focus, contrast per theme, comparison table scrolls in its container, headings in order, `<details>` operable by keyboard, no console errors, no failed requests, no third-party hosts, CSP unchanged.
- PHP tests: key parity, no price text in templates, sitemap includes/excludes correct URLs, noindex where required, canonical/hreflang per page, structured data valid JSON, unique titles/descriptions across all pages, no duplicate paragraphs across service pages.
- Add a link-integrity check (every internal link resolves 200/redirect).

---

**Topic ownership (avoid competing pages).** Each topic has one owning page; others link to it, no repeated blocks: price & scope & FAQ on cost → `/pricing`; process & ownership/handover → `/process`; template-vs-custom → the two service pages (pricing links to them; no separate guide); `FAQPage` markup on one page per question. Home carries only short teasers.

**One-person capacity guard.** Cap Phases 1–2 at three new pages (`/pricing`, `/process`, `/about`); run the full browser matrix at release and `--quick` per page; add guides only once the owner shows capacity for the twice-yearly review.

## L. Roadmap (12–18 months)

Columns: purpose · value · complexity (S/M/L) · dependency · public now? · can wait?

### Phase 0 — Launch blockers (week 0–2)

| Item | Purpose | Value | Cx | Depends on | Public now | Can wait |
|---|---|---|---|---|---|---|
| Fill company/legal placeholders, `PRICE_VAT_MODE`, retention etc. | Legal readiness | High | S | owner | yes | no |
| HTTPS, `APP_URL`, `TRUST_PROXY`, backups of `storage/` | Production | High | S–M | hosting | yes | no |
| Real Telegram smoke test, admin credentials | Operations | High | S | owner | n/a | no |
| Search Console + sitemap submission | Visibility | Med | S | live site | yes | no |
| 1200×630 OG image | Sharing | Low–Med | S | design asset | yes | yes |

### Phase 1 — Foundations (month 1–2)

| Item | Purpose | Value | Cx | Depends on | Public now | Can wait |
|---|---|---|---|---|---|---|
| Component extensions (§H) | Reuse for all new pages | High | M | none | n/a | no |
| `/pricing` | Decision support, SEO for price intent | High | M | owner 1,3,4 | yes | no |
| `/process` | Reduce friction | High | S–M | owner 6 | yes | no |
| Home additions (strip, FAQ, differentiators, link to pricing) | Funnel | Med–High | S | pricing, process | yes | partially |

### Phase 2 — Trust (month 2–3)

| Item | Purpose | Value | Cx | Depends on | Public now | Can wait |
|---|---|---|---|---|---|---|
| `/about` | Person behind the site | High | S | owner 7 | yes | no |
| Accessibility + performance baseline report (internal) | Evidence for later claims | Med | S–M | none | no | yes |

### Phase 3 — Commercial depth (month 3–6)

| Item | Purpose | Value | Cx | Depends on | Public now | Can wait |
|---|---|---|---|---|---|---|
| Service-page template + Template & Custom pages | Specific search intent | Med–High | M | pricing | yes | partly |
| Case-study system (hidden) | Ready for proof | Med | L | none | no | yes |
| Redesign page | Search intent | Med | M | owner process | maybe | yes |

### Phase 4 — Proof (month 6–12)

| Item | Purpose | Value | Cx | Depends on | Public now | Can wait |
|---|---|---|---|---|---|---|
| First 2–3 real case studies, then publish portfolio (remove noindex, add nav/footer/sitemap) | Authority | High | M (writing) | real projects + consent | after ≥2–3 | no |
| Testimonials block (real, permissioned) | Trust | High | S | real quotes | when real | yes |
| Maintenance page | Recurring revenue clarity | Med | S | defined offer | when defined | yes |

### Phase 5 — Content (month 9–18, optional)

| Item | Purpose | Value | Cx | Depends on | Public now | Can wait |
|---|---|---|---|---|---|---|
| 4–6 evergreen guides | Long-tail SEO | Med | M each | capacity | one at a time | yes |
| Services hub | Navigation | Low–Med | S | ≥4 services | later | yes |
| Standalone FAQ | Navigation/SEO | Low–Med | S | ≥12 real Q | later | yes |
| Accessibility statement | Compliance/trust | Med | S | actual audit | when true | yes |
| Local page (one) | Local intent | Low–Med | S | owner 8 | only if real | yes |

**Regular maintenance (owner time):** quarterly price/scope review; twice-yearly guide review; check legal pages when anything changes; review competitor prices annually.

---

## M. Exact implementation order (after approval)

Model use: **Sonnet 5.5 implements**, **Opus 5.5 reviews each phase's diff before commit.**

1. **Phase 0 checklist** (no code beyond config): owner fills `.env` values and legal placeholders.
2. **Components**: add `.page-hero`, `.compare`, `.steps`, `.faq`, `.checklist`, `.eyebrow`, `.note` to `style.css` with light/dark parity; run the style-diff tool to prove no existing component changed; add a hidden internal "styleguide" test page only inside the test suite, not public.
3. **Router/templates**: generic inner-page layout; route registration; sitemap and nav/footer entries behind a data-driven page registry so adding a page edits one place.
4. **`/pricing`** (copy CS then EN → templates → tests → Chrome matrix).
5. **`/process`**.
6. **Home additions**.
7. **`/about`** (only with owner content).
8. Regression pass: PHP suite, Chrome matrix incl. 430/820, style-diff, link check, security/exposure check, CSP check.
9. Commit per page group (one concern per commit); no push/deploy without instruction.
10. Next phase items follow the same loop.

---

## N. Risks and honest limits of this research

- **Keyword demand is unmeasured.** Query examples above are plausible clusters, not volumes. Verify before building search-targeted pages.
- **Competitor data is a one-day snapshot** of public pages; claims, ratings and prices are theirs and may change.
- **Webovky48's model may be succeeding or struggling** — this research cannot tell; it only shows structure.
- **Price positioning** is shown, not recommended; scope units differ.
- **Czech copy quality** must be reviewed by a native speaker.
- **Authority gap** (no portfolio, no reviews) is real. The plan closes it by creating real projects and publishing them with consent, not by simulating them.
