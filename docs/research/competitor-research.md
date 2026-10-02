# Pixelite.cz — Competitor Research

*Research snapshot: 2 Oct 2026. Companion documents: [`pixelite-site-strategy.md`](pixelite-site-strategy.md) (sitemap, page specs, roadmap) and the six full URL inventories in [`inventory/`](inventory/). This file is documentation only — no site code was changed.*

> **Scope note.** The brief says "three competitors" in places but lists **six sites**. All six were researched: Pixelfield, Pixelmate, Applifting, Bewow, Webovky48, Scalery.
> **Copyright note.** Everything below is paraphrased. Page titles appear only as inventory metadata. Numbers quoted from competitors are *their own published claims or prices*, reported as observations, not as endorsements or as facts about Pixelite.
> **Verification status.** Competitor prices and tracker observations were collected in one session from public pages and rendered loads; where a figure lacks a stored source URL/screenshot in the inventories (e.g. Bewow and Pixelfield 'from' prices), treat it as *unverified until re-checked*. Statements like 'not found' mean 'not found on the pages crawled'.
> **No ranking.** The companies are described by the patterns they use. None is declared "better".

---

## 0. Summary of what the research shows

1. **The market splits into four different games**, and Pixelite is in only one of them:
   - *Product/engineering studios* for startups and mid-size firms (Pixelfield, Pixelmate, Applifting): large content estates, long service pages, case studies, ratings, careers, blogs.
   - *Creative/brand agency* (Bewow): one dramatic single-page site.
   - *Productised "fast cheap web"* (Webovky48, owned by the same group as Bewow): fixed prices, a speed promise, a price calculator, and 68 templated local/industry landing pages.
   - *SaaS platform with a done-for-you service* (Scalery): monthly tariffs, a free trial, a 5-page client-rendered site.
2. **Pixelite's nearest rival is Webovky48**, not the engineering studios. It publishes one-page / up-to-5-page / up-to-10-page packages at 11,900 / 18,900 / 28,900 Kč, a 'final price, no additions' promise, 48-hour delivery, and instalment/rental options. Pixelite's Basic tier (from 32,900 Kč) is priced *above* its 10-page tier, so Pixelite must win on clarity, directness and scope — not on price. (§4.5, §6)
3. **Price transparency is rare and valuable.** Of six sites, two publish concrete package prices (Webovky48, Scalery), two publish "from" prices per service (Bewow, Pixelfield service pages), one publishes a cost-explainer (Pixelmate), one publishes none (Applifting). Pixelite's three public prices already put it in the transparent group; a dedicated pricing page can extend that lead.
4. **Page count is not the winning move.** Webovky48's 85 pages are 80% templated (city/region/industry) with 48–88% of text blocks shared between sibling pages, and 81 of 85 pages have no navigation or footer link. Pixelfield (207 pages) and Pixelmate (269) are mostly blog archives. Pixelite should not copy that shape.
5. **Trust is built four ways**: client logos + ratings (Pixelfield, Pixelmate, Applifting), case studies with technology tags (all three studios), real faces of the people (Bewow, Webovky48, Applifting "crew"), and price/scope certainty (Webovky48, Scalery). A one-person studio can credibly use faces, scope certainty and (later) real projects. It cannot credibly use logos, headcount or tenure — and should not.
6. **The big studios run heavy tracking** (session replay, visitor-identification, ad pixels, several tag managers). Pixelite currently sets **no** analytics or marketing cookies. That is a verifiable, defensible difference — only for as long as it stays true.
7. **Gaps Pixelite can fill without pretending to be larger** (§6): template-vs-custom explained plainly; "included / not included" stated openly; ownership, hosting and domain explained; what happens after ordering; direct access to the person doing the work; privacy-respecting site.

---

## 1. Method and coverage

| Site | Seeds | Crawl method | Pages found | Cap hit? | Notes / what could not be verified |
|---|---|---|---:|---|---|
| pixelfield.cz | robots.txt, 3 sitemap files (64 URLs), homepage | Static HTML crawl, link-following | **207** (+10 redirect aliases) | No | Blog (140 incl. categories/pagination) is **not in the sitemap**. 170 external hosts linked. |
| pixelmate.cz (/cs, /en) | robots.txt, 4 sitemap files (236 URLs), `/en` + `/` | Static | **269** (+1 alias) | No | The sitemap advertises **German pages on `pixel-mate.de`, which is a parked/for-sale domain** (Sedo, HTTP 403 to the crawler) → 39 URLs excluded as not real. 3 own URLs are broken (`/portfolio`, `/reference-internal-systems`, and a malformed `mailto` link rendered as a path). Several `/en/…` pages still carry Czech titles/content. |
| applifting.io | robots.txt, sitemap (140 URLs), homepage | Static (Framer server-renders) | **158** (+2 aliases) | No | 59 sitemap URLs had **no inbound link visible in static HTML** (older articles/events are likely loaded dynamically in hub pages — linkage unverified). Footer links are not in a semantic `<footer>`, so footer-vs-content attribution is approximate for this site. **No privacy/legal page was found** in the crawl. |
| bewow.cz | robots.txt, sitemap (8 URLs), homepage | Static + rendered check | **8** | No | One-page site. Hero is a very tall scroll-driven section (full-page capture shows large empty space; not a bug in their site, an artefact of capturing scroll-triggered animation). |
| webovky48.cz | robots.txt → `sitemap-index.xml` (85 URLs) | Static | **85** | No | Part of "Bewow Agency Group". |
| scalery.cz | robots.txt (no sitemap; `/sitemap.xml` returns the app shell) | **Rendered** in headless Chrome (client-rendered SPA) | **4** (+ `my.scalery.cz` login app, own subdomain) | No | Every route returns the **same `<title>`** (only the H1 changes). |

**Rendering decision, with evidence.** I compared the internal links in each homepage's static HTML against the same page rendered in Chrome. Pixelfield 54/54, Applifting 15/15, Bewow 5/5, Webovky48 4/4 matched (0 missing); Pixelmate differed only by one PDF (checked by HEAD). So **static crawling was sufficient for five sites**; Scalery was crawled rendered.

**Politeness.** Honest user-agent, 3 concurrent requests, ~220 ms delay, `robots.txt` `Disallow` rules honoured (none blocked a page), page cap 1,500 per site (never reached). Documents (PDF) were checked with HEAD only, not downloaded.

**URL hygiene.** Hosts normalised (www/non-www), `http→https`, fragments and tracking parameters stripped, redirects keyed on the final URL, redirect aliases listed separately, canonical tags stored but not used to merge URLs. External hosts are listed separately, not counted as pages.

**Classification.** URL patterns + title/H1 heuristics with per-site rules; "Other" used when unsure. I hand-reviewed ~55 sampled rows (about 10–14 per large site) against actual titles; this surfaced and fixed one systematic error (blog pagination counted as articles).

**Not done / limits.** No logged-in areas; no keyword-volume or backlink data (not available to this research — see §8 for what to verify before building SEO pages); design observations come from desktop (1440) and mobile (390) renders and computed styles, not from user testing; competitor statistics, ratings and prices are *their* claims.

### 1.1 Inventory totals

| Site | Unique pages | Case studies | Articles | Service/solution pages | Other notable |
|---|---:|---:|---:|---:|---|
| Pixelfield | 207 | 17 | 123→112 *(+11 pagination, 18 category pages)* | 33 | careers 1+3, team 3, events 1 |
| Pixelmate | 269 | 46 *(CS + EN)* | 155 *(107 CS + 48 EN)* | 44 + 3 solutions | pricing explainer ×3, consultation page |
| Applifting | 158 | 25 | 94 | 1 + 1 (+ services hub) | 19 events, 5 ebooks, press, 2 job pages |
| Bewow | 8 | 0 (work shown inline) | 0 | 0 (services inline) | AI-assistant info page ×2 |
| Webovky48 | 85 | 0 | 0 | 11 + 34 industry | **20 city + 14 region pages** |
| Scalery | 4 | 0 | 0 | 0 | login app on subdomain |

*(Exact per-type counts and every URL: see the inventory files.)*

### 1.2 Complete URL inventories (appendices)

Each file lists **every discovered internal URL** with title, type, parent section, navigation flag, footer/content-only flag, purpose, important CTA and SEO/content role, plus redirect aliases, broken URLs, off-host URLs, documents and external hosts.

- [`inventory/pixelfield.md`](inventory/pixelfield.md) — 207 pages
- [`inventory/pixelmate.md`](inventory/pixelmate.md) — 269 pages
- [`inventory/applifting.md`](inventory/applifting.md) — 158 pages
- [`inventory/bewow.md`](inventory/bewow.md) — 8 pages
- [`inventory/webovky48.md`](inventory/webovky48.md) — 85 pages
- [`inventory/scalery.md`](inventory/scalery.md) — 4 pages

### 1.3 Pages reachable by search/sitemap but not by the main navigation

| Site | Finding |
|---|---|
| Pixelfield | 140 blog URLs (articles, categories, pagination) are **not in the sitemap** yet are linked in content; commercial pages are all reachable from the global mega-menu/footer. Ten legacy keyword URLs (e.g. mobile-app promotion/monetisation, strojové učení, VR/AR aliases) now **redirect** to a main service page — consolidation rather than duplication. |
| Pixelmate | A set of **older Czech landing pages sit outside nav and footer** (web, e-shop, blockchain, IoT, DevOps, CRM, HR, process automation, VR; ~17 pages, not in the sitemap) and are reachable only through in-content links. Dozens of case studies are reachable from the work index, not the menu. |
| Applifting | 59 sitemap URLs without a visible inbound link (see limits above); events/ebooks/press are linked from the footer-style area of the homepage, not the top nav (top nav has 3 items + CTA). |
| Webovky48 | **81 of 85 pages** (all offers, city, region and industry pages) are outside the top nav/footer; they are reachable via in-content links and the sitemap. This is the clearest example of a deliberate "landing pages for search" strategy. |
| Bewow, Scalery | Too small to have such pages. |

---

## 2. Per-competitor analysis

### 2.1 Pixelfield (Prague / London studio — AI, apps, web, design)

**Visual system.** Dark forest-green/near-black hero with a lime-yellow headline, then off-white content sections; lime accents and CTAs. Display face is a commercial neo-grotesque (PP Neue Montreal) paired with a monospace for small tags (Tronica Mono). H1 ≈ 64 px / weight 500, body 16 px. A scrolling keyword ticker band separates sections; numbered process steps (01, 02, …); full-bleed coloured case panels mixing photography and device mockups. Responsive breakpoints at 479/640/768/900/1024/1100/1200/1920. One keyframe animation; motion is mostly marquee and hover. Mobile hides the menu behind a "menu" control with two persistent CTAs.

**UX.** Mega-menu (services/references/about/FAQ/contact) plus a persistent "book a call" button; a 40-link footer acts as a second sitemap. Service pages are long (≈2,000–4,000 words) with recurring blocks: outcomes, sub-services, process, pricing anchors, FAQ-style detail, closing CTA. Case-study pages are short (≈430 words).

**Content.** Positions as a reliable partner for AI, apps and product design; leans on years in business, review ratings (Google, Trustpilot, Clutch displayed as stars), a client-logo row, a partner page, a quote from the design lead, and a three-person leadership page. Heavy keyword targeting (city + service) in titles; blog of ~112 articles plus category archives.

**Conversion.** Primary CTA: *book a call*; secondary: *get an estimate*, *start a project*. No form on the homepage; contact page is a project-description form. Price anchors appear on service pages as "from" amounts (examples observed: from ~30,000 Kč up to ~280,000 Kč, depending on service). Social proof: star ratings, logos, case studies (17).

**Tracking/consent (observed in rendered DOM).** Tag manager, Google Analytics, Hotjar, Microsoft Clarity, a Pipedrive chat widget, and two visitor-identification services. No consent banner was detected at load.

### 2.2 Pixelmate (Prague studio — apps, web, software, AI; CS/EN)

**Visual system.** Saturated electric-blue canvas across the whole homepage, huge white headline (≈ 88 px / 600) with one green highlighted phrase, Montserrat throughout. Ten-item icon grid of services directly under the hero, a video/drone "team" clip, a client-logo marquee, statistic tiles, a two-column masonry of project cards, press/"achievements" logos. Next.js, ≈100 inline SVGs, six video elements, six keyframe animations. Breakpoints min 640/768/1024/1280/1536.

**UX.** Very short top nav (4 items: why us, work, contact, language) with dropdowns; services are surfaced by the homepage icon grid and the footer. Large body of content: 25-ish case studies × 2 languages, ~155 articles. A dedicated **cost explainer page** ("how much does custom app/web development cost") plus a **non-binding consultation page**. English section partly untranslated (Czech titles/content on several `/en/…` pages).

**Content.** Product-studio narrative: strategy + design + development + marketing under one roof; founder/team page, "why Pixelmate", "how we work", Clutch rating and press mentions (Forbes, CzechCrunch) as authority; long, structured case studies (intro, task, scope of work, features, visual design, technologies used, press, client interview, CTA).

**Conversion.** Primary CTA: *contact us* (form framed around a coffee meeting); secondary: see all projects, read more articles. Pricing communicated as an *explainer with estimated ranges and "what drives the cost"*, not a price list. Social proof: logos, press, case studies.

**Tracking/consent.** Tag manager, gtag, LinkedIn insight, Meta pixel; a consent library is present (CSS variables) but no banner at load in the rendered DOM.

### 2.3 Applifting (Prague / London product studio, English-first, Framer-built)

**Visual system.** White, very heavy black display type (a paid typeface, ABC Ginto; H1 ≈ 88 px / 800), monospaced uppercase micro-labels for tech tags, playful space/"mission log"/"pilots" vocabulary, a 3D orange blob illustration, device mockups on coloured panels, a headline statistics row, a video block, client-logo marquee. Framer site with generous breakpoints (≈352 → 1919). Almost no CSS keyframes (animation via Framer runtime). Cookie banner bottom-right with two first-layer buttons ("Customize", "Accept all").

**UX.** Top nav of three items + a "let's talk" button; deeper content lives in "brainspace" (hub for blog/events/ebooks/press) reached from the footer area. 25 case studies in a consistent format (about, outcome, mission, challenge, solution, testimonials) with technology tags. A German-language fintech landing page and a separate "vibe-coded app to production" offer page show targeted landing pages outside the main nav.

**Content.** Product-engineering focus (AI-assisted development, product trios, legacy modernisation), lots of thought-leadership (94 articles), 19 events, 5 ebooks, press resources, a long careers page (employer-brand heavy) with role pages.

**Conversion.** Primary: *let's talk* (contact form). Lead magnets: ebooks, events/webinars. **No prices published.**

**Tracking/consent.** Cookie-consent tool + Microsoft Clarity, Smartlook (session recording), Leadfeeder (visitor identification), LinkedIn insight, tag manager, Metricool.

### 2.4 Bewow (Czech creative/brand agency — one-page site, CS/EN)

**Visual system.** Near-black (#0b0b0b) canvas with a single acid-lime accent, huge *light* display type (≈ 138 px / weight 300) with one italic highlighted word, frosted "pill" tags, a 3D sculptural hero with particles (canvas/WebGL), floating bottom CTA bar showing a project count, AVIF imagery, scroll-driven animation (13 running animations). Built with Astro; analytics: tag manager only.

**UX.** Everything is on one page: services → selected work → client logos → team → FAQ → enquiry. Numbered anchor nav (what we do / work / clients / who we are) + language switch + "start a project". The only extra pages are legal pages and an **information page for AI assistants** (company facts written for LLMs), in both languages.

**Content.** Brand-led, confident tone; five named people with photos; testimonials presented as the clients' own voice; FAQ.

**Conversion.** Primary: *start a project*. The enquiry form pairs a **service selector that shows "from" prices** (identity, web, social, content) with a **budget-range selector** — the same qualification pattern Pixelite's order form already uses. Social proof: client logos, "100+ projects", testimonials.

### 2.5 Webovky48 (Bewow Agency Group — fast fixed-price websites, Czech only)

**Visual system.** Dark hero with a 3D laptop/globe render; warm off-white sections; orange (#ff6a00) as the single action colour; Quicksand (rounded sans) + JetBrains Mono for labels; H1 ≈ 64 px / 700, body 17 px. Static Astro site, very fast. A four-point time-stamped mini-timeline sits in the hero (0 h → 2 h → 24 h → 48 h), a ticker band, six feature cards, three package cards (middle one highlighted), a dark "rental / instalments" banner, three founder portraits, six real built-site screenshots with their domains, and a four-step process.

**UX.** Anchor nav (how it works / what's included / pricing / references / people / FAQ) and one persistent action: **"calculate price"**. A multi-step form/calculator ends on a thank-you page. Every landing page repeats the same pricing block and CTA.

**Content.** Radically concrete: fixed package scopes (sections/pages), "what's included" and an explicit list of **what other agencies often bill separately**, a short "what raises the price" list (e-shop, languages, extra pages…), statement that prices are final. Texts are written for the customer ("we write the copy for you"). 34 industry pages (barbers, accountants, physiotherapists, builders…), 20 city pages and 14 region pages.

**Quantified template check.** Sibling city pages share ~54–88% of their text blocks; region pages ~79%; industry pages ~48–52%; city-vs-industry only ~26%. Average length ≈ 1,000–1,150 words per templated page, ≈1,270 for core pages.

**Conversion.** Primary: *calculate price* / *I want a web in 48 h*; secondary: *choose START / BUSINESS / PREMIUM*, *web on instalments* (rental). Prices: 11,900 / 18,900 / 28,900 Kč, e-shops from 34,900 Kč; hosting and maintenance sold in-house. Social proof: real built sites + founders' faces; no logo wall.

**Tracking/consent.** Tag manager only; a cookie page exists.

### 2.6 Scalery (SaaS CRM + AI platform with a done-for-you build service)

**Visual system.** White, near-black type, a single lime highlight chip behind a headline phrase, Space Grotesk + JetBrains Mono for micro-labels, H1 ≈ 110 px / 600, feature grids with mono icons, dark and light cards side by side, a four-metric stats row. Twelve keyframe animations, no raster images at all (pure CSS/SVG). Built as a client-rendered React app (shadcn-style design tokens).

**UX.** Single long page with five anchors (pillars, platform, AI, tariffs, FAQ) + login + "start free". Product tour first, then a 14-day onboarding timeline, then three monthly tariffs (1,290 / 2,970 / 4,970 Kč) with a monthly/annual toggle, testimonials and FAQ.

**Content.** Anti-"tool sprawl" story (replace ~8–12 tools), a build-it-for-you service as one of three pillars, pitched as cheaper than a typical agency. Legal pages in-app; **identical `<title>` on every route** and no sitemap — weak search footprint by design or neglect (cannot tell).

**Conversion.** Primary: *start free* (14 days, no card). Chat widget (LeadConnector). Consent banner offers "accept all" / "only necessary" side by side.

---

## 3. Cross-cutting patterns (what the market commonly does)

| Pattern | Seen at | Pixelite relevance |
|---|---|---|
| Persistent primary CTA in the header | All six | Pixelite already has "Start a project →". |
| One memorable action label (not "Contact") | Book a call · Let's talk · Calculate price · Start free · Start a project | Keep one primary action; add a distinct secondary ("Non-binding consultation") — already present. |
| Price transparency | Webovky48, Scalery (full) · Bewow, Pixelfield (from-prices) · Pixelmate (explainer) | Pixelite already shows 3 "from" prices — extend with scope and exclusions. |
| Productised scope ("up to N pages/sections") | Webovky48 | Pixelite's cards lack scope units (pages/sections). Owner decision; see strategy doc. |
| "What's NOT included / often billed separately" | Webovky48 | A strong honesty pattern Pixelite can adopt in its own words. |
| Logos + ratings + headcount stats | Pixelfield, Pixelmate, Applifting, Scalery | **Not available to Pixelite — do not fake.** |
| Faces of the people | Bewow, Webovky48, Applifting, Pixelfield | Pixelite can show one real person. |
| Case studies with tech tags | Pixelfield, Pixelmate, Applifting | Needs real projects; build system now, publish later. |
| Long service pages (2,000–4,000 words) | Pixelfield | Not realistic for a solo studio at scale; 2–3 deep pages are. |
| Programmatic local/industry pages | Webovky48 (68 pages) | **Avoid at scale**; see doorway-page risk. |
| Blog/thought leadership | Pixelfield, Pixelmate, Applifting | Only if maintainable. |
| Lead magnets (ebooks, events) | Applifting | Later, if ever. |
| Heavy tracking | Pixelfield, Pixelmate, Applifting, Scalery | Pixelite's no-tracker stance is a differentiator. |
| Consent UX | Applifting: two first-layer buttons; Scalery: accept-all / only-necessary | Pixelite already offers Accept / Reject with equal weight. |
| Technical micro-labels (mono caps) | Pixelfield, Applifting, Webovky48, Scalery | A fitting, cheap visual device for Pixelite's "technical" tone. |
| Motion | Marquees, scroll-driven heroes, 3D, particles | Pixelite already has a CSS-only laptop; keep motion restrained and respect reduced-motion. |

### 3.1 Design observations compared

| Site | Canvas | Accent | Display type | H1 | Signature device |
|---|---|---|---|---|---|
| Pixelfield | dark green → off-white | lime/yellow | PP Neue Montreal + mono | ~64/500 | ticker band, numbered steps |
| Pixelmate | electric blue | green highlight | Montserrat | ~88/600 | full-bleed colour + service icon grid |
| Applifting | white | black / orange blob | ABC Ginto (heavy) + mono | ~88/800 | space vocabulary, mono tech tags |
| Bewow | near-black | acid lime | light display (Delight) | ~138/300 | 3D sculpture, floating CTA bar |
| Webovky48 | dark → warm white | orange | Quicksand + JetBrains Mono | ~64/700 | price cards, hero timeline |
| Scalery | white | lime chip | Space Grotesk + mono | ~110/600 | highlighted phrase, product cards |

Pixelite's own system (Lato, blue/violet/purple on a pale canvas, pill buttons, soft shadows, now with dark mode) overlaps with **none** of these palettes — it reads as friendlier and lighter than the studios and calmer than the fast-web brands. That is a usable distinction.

---

## 4. Pixelite today (verified against the repository)

*(Source: `CLAUDE.md`, `src/bootstrap.php` routes, templates; commits `06d2d1a`, `b650101`, `478fa50`.)*

### 4.1 Public routes (each exists in `/cs/…` and `/en/…`)

`/` (landing with hero, 3 packages, Dreamers Ad Credits strip, non-binding consultation, contact CTA, band) · `/order` (+ `/order/sent`, noindex) · `/contact` · `/privacy` · `/cookies` · `/terms` · `/portfolio` (**hidden**, noindex, unlinked, placeholder data) · `/robots.txt` · `/sitemap.xml` (hreflang alternates). Staff: `/admin/login`, `/admin`, `/admin/orders/{id}`.

### 4.2 Technology and constraints

Plain PHP 8.3, SQLite, Apache in Docker, no framework or Composer, server-rendered templates, vanilla JS (no dependencies), self-hosted fonts and images, strict CSP (no inline script/style), design tokens in one stylesheet with a **full dark theme**, Light/Dark/System switch, cookie/consent system with an **empty optional-services registry**, bilingual i18n by URL prefix with enforced key parity, Telegram order notifications with persisted fallback, order **pricing snapshots**, 217 PHP checks and ~11,570 browser checks (at commit 478fa50).

### 4.3 Commercial facts on the site now

Template website (from 2,999 Kč), Basic website (from 32,900 Kč), Custom website (from 64,900 Kč); starting prices with a visible VAT placeholder until `PRICE_VAT_MODE` is set; budget ranges from under 10k to 100k+ Kč; a promotional advertising credit (up to $100 for eligible orders from 30,000 Kč, conditions owner-pending); a non-binding consultation section; company identity in the footer (name, IČO, registered office in Karlovy Vary, email, phone).

### 4.4 What is missing relative to the market

No pricing detail page, no process/"how we work" page, no about/founder page, no FAQ, no case studies (by design), no per-service pages, no guides/blog, no explicit included/not-included statement, no ownership/hosting/handover explanation.

### 4.5 Where Pixelite sits on price, honestly

| Offer | Pixelite | Webovky48 | Bewow (web design & development) | Pixelfield (examples, "from") |
|---|---|---|---|---|
| Entry / template-style | **from 2,999 Kč** (template implementation, limited) | — (cheapest: 11,900 Kč one-page, ≤6 sections) | from 25,000 Kč | — |
| Small company site | **from 32,900 Kč** (Basic) | 18,900 Kč (≤5 pages) · 28,900 Kč (≤10 pages + blog + editor access) | included in above | web service pages show "from" anchors in the tens of thousands |
| Custom / bespoke | **from 64,900 Kč** | e-shop from 34,900 Kč | — | "from" anchors for apps/AI range from ~30,000 to ~280,000 Kč |

*(All figures are the competitors' published prices as observed on 2 Oct 2026; scope units differ, so this is a positioning map, not a like-for-like comparison.)* The takeaway: the **template tier undercuts everyone**, the **Basic tier sits above Webovky48's most comparable package**, and the **Custom tier overlaps the studios' entry anchors while offering one-person access**. Whether that is the right price architecture is an owner decision; this research only shows the context.

---

## 5. Strategic position

**Pixelite is not an enterprise consultancy and not a 48-hour factory.** The defensible position is: *clean, affordable websites with transparent pricing, built and owned by one accountable person you can talk to directly.*

What Pixelite must not do (and the competitors' examples show the temptation): invent client logos, headcount, years in business, awards, ratings, case-study results, delivery guarantees or speed promises, or a "team". Authority has to come from **transparency, specificity, quality of the site itself, and — as soon as they exist — real projects**.

What Pixelite can credibly claim today, if the owner confirms each statement: prices are public; the site is built to modern standards (measured, not asserted — see strategy doc §I); there is no tracking beyond what is strictly needed; orders go straight to the person doing the work; the process and what is included are written down.

---

## 6. Market gaps Pixelite can fill

| Gap observed | Evidence | Pixelite answer (honest, small) |
|---|---|---|
| **Template vs custom** not found explained on the pages crawled | Webovky48 sells "custom in 48 h" without contrasting it with a template; studios sell only bespoke | One page that states plainly what each tier means and does not mean |
| **What's not included** is rarely listed | Only Webovky48 lists it | A candid "not included / billed separately" list per package |
| **Ownership, hosting, domain, handover** are glossed over | Hosting appears as an upsell at Webovky48; not found elsewhere on the pages crawled | A short ownership & handover explanation |
| **What happens after you order** | Webovky48 has a 4-step strip; studios use generic "process" blocks | A real process page with honest ranges (only verified timings) |
| **Direct access** | Large studios hide the builder behind sales; Webovky48/Bewow show faces | An About page that names the person who writes the code |
| **Privacy-respecting site** | Three of six studios showed session replay, visitor-ID or ad-pixel scripts in one rendered load from Prague (Pixelfield, Pixelmate, Applifting); observation only, no legal judgement | A truthful one-line statement, kept true |
| **Maintenance explained** | Hosting/service appears only as a Webovky48 upsell | Define a maintenance offer first; then explain it |
| **Accessibility, performance, security basics** | Rarely discussed | Short evidence-based statements (measure first) |

---

## 7. Competitor-claim handling rules for Pixelite copy

1. Never reuse competitors' wording, structure or slogans.
2. Never imply a number (clients, years, team, ratings) that Pixelite cannot prove.
3. Speed/price promises only where the owner will honour them in writing.
4. Every page must have a single clear next action (order, consultation, or read on).

*(Continues in [`pixelite-site-strategy.md`](pixelite-site-strategy.md).)*
