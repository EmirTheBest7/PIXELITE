# Localization audit – Russian and Ukrainian (2026-10-22)

Scope: the whole public experience in `en`, `cs`, `ru`, `uk`, new design and `/legacy`. **Everything below is in the working tree only – nothing was committed, pushed or deployed.**
Terminology and rules: [`TERMINOLOGY.md`](TERMINOLOGY.md). "Verified" means covered by a test or checked in a real browser in this session; "remaining" means not done or not decidable by the code.

## 1. Russian – major improvements (`lang/ru.php`, every string reviewed in context)
* One consistent vocabulary: a project request is a **заявка** (было: «запрос»), the account is **аккаунт**, final amount is **итоговая стоимость**, starting prices stay "От … / стартовая цена", catalog = **«Дизайны сайтов»**, live preview = **«Интерактивное демо»**, animated card video = **«превью»**.
* Natural commercial register: «Оставить заявку», «Отправить заявку», «Консультация без обязательств», «Есть идея проекта?»; errors tell visitors what to do («Проверьте адрес электронной почты – похоже, в нём ошибка», «Сократите текст»).
* Gender-neutral wording instead of `(а)`: «Мне известно, как используются мои персональные данные», «Пока не знаю», «Не знаю, какой пакет мне подойдёт».
* Legal text re-worded without changing substance (контролёр instead of оператор for the GDPR *controller*, «Раздел для сотрудников», «результат работы», рекламация/претензия kept with the Czech term in brackets, IČO/DIČ/DPH labels explained but not localized).
* Abbreviations expanded (`эл. почта` → «Электронная почта»); `Посадочная страница` → «Лендинг».

## 2. Ukrainian – major improvements (`lang/uk.php`, edited independently of the Russian)
* Same terminology map with Ukrainian forms: **заявка**, **обліковий запис**, **остаточна вартість**, «Дизайни сайтів», «Інтерактивне демо», «прев’ю»; `проєкт` throughout, apostrophe `’`.
* Russian-influenced constructions replaced: «будучи ввійшовшим в обліковий запис» → «після входу в обліковий запис», «Вперше в нас?» → «Ви в нас уперше?», «Сміливо ставте запитання» → «Поставте нам будь-яке запитання», «позиції» → «вакансії», «Створено на» → «Технології», «Ще дизайни» → «Більше дизайнів».
* Neutral forms instead of `(а)/(ла)` («Мені відомо…», «Ще не знаю»); titles of the Russian and Ukrainian team pages differ («Наша команда» / «Знайомтеся з командою») because page titles must be unique.
* A test asserts that the Ukrainian text is not a copy of the Russian one and that neither language contains the other's letters.

## 3. Mistranslations and meaning errors corrected
| Where | Before | After |
|---|---|---|
| uk Asterform feature | «дворядковий розділ філософії» (*two-row*) | «розділ про філософію студії у дві колонки»; ru «двухколоночный раздел о философии студии» |
| ru/uk portfolio title | «Работы» / «Роботи» (*Works*) | «Наши проекты» / «Наші проєкти»; subtitle «Избранные работы» / «Добірка робіт» |
| ru/uk catalog labels | «Дизайны» | «Дизайны сайтов» / «Дизайни сайтів» |
| ru/uk comparison | «Пакеты рядом» | «Сравнение пакетов» / «Порівняння пакетів» |
| ru/uk feature "contact call-to-action" | «контактный призыв к действию» | «Контактная форма или кнопка для связи» / «Контактна форма або кнопка зв’язку» |
| ru/uk "low running costs" | «расходы на содержание» | «затраты на обслуживание сайта» / «витрати на обслуговування сайту» |
| ru/uk live preview | «Живой предпросмотр» | «Интерактивное демо» + hint "это работающий сайт, а не картинка: прокручивайте и нажимайте" |
| ru/uk account, no projects | «С вашим аккаунтом пока не поделились ни одним проектом» | «В вашем аккаунте пока нет ни одного проекта» / «До вашого облікового запису ще не додано жодного проєкту» |
| ru/uk project form | «запрос» / «форма запроса» | «заявка на проект», «Отправить заявку» |
| ru/uk Lumora hero | «рисование» портрета (literal *painting*) | portrait "проявляется под курсором" |
| ru/uk project types | «Индивидуальное ПО», «Посадочная страница» | «Разработка ПО под заказ», «Лендинг» |
| ru/uk 3+ months | «Через 3 месяца и позже» | «Более чем через 3 месяца» |

## 4. Localization gaps fixed outside the dictionaries
* **Portfolio** (`config/portfolio.php`): placeholder entries had only `en`/`cs`, so ru/uk cards rendered blank. Added ru/uk text (still clearly placeholders, no invented projects) and the new `localized()` helper (language → English → any filled language) used by both designs.
* **Careers (database)**: a vacancy is "translated" only when title *and* description exist for that language; otherwise the **whole** English text is shown with `lang="en"` and a visible line (`careers.english_only`, 4 languages). Before, a Ukrainian title could sit over an English description with no hint. Records untouched; no schema change.
* **Language and design switches lost the query string** (`?template=lumora`, `?package=basic`, gallery filters). Both now keep it (`current_query()`, GET only, `next` dropped, ≤300 bytes). Canonical, hreflang and sitemap still never carry a query.
* Hard-coded English removed: legal-page "On this page" label (cs/ru/uk got English/Czech only) → `a11y.toc`; mobile menu button "Menu" → `nav.menu`; the hidden anti-spam field label "Leave this empty" → `a11y.honeypot`.
* Gallery "A–Z" sorting now uses Russian and Ukrainian collation (`ru_RU`, `uk_UA`) instead of English.
* JSON-LD `WebSite.inLanguage` listed only `en, cs`; it now lists all four.
* Audited and found correct/intentional: JavaScript (all labels arrive through `data-*`/server-rendered attributes; only the staff-only `admin.js` has English), cookie banner and dialog, validation messages (all keys), mailto subjects, `<html lang>`, `og:locale`, hreflang, breadcrumbs, 404/500 pages, account empty states.

## 5. English and Czech changes (and why)
* New keys added to **all four** files: `a11y.toc`, `a11y.honeypot`, `nav.menu`, `careers.english_only`.
* Privacy §4 (EN + CS): "request form **and the staff login**" → "request form, **customer accounts** and the staff login". The rate-limit hash does cover customer sign-in/registration (see `CLAUDE.md`); the ru/uk text already said so, so the four languages are now equal and accurate. No behavior changed.
* **Not changed on purpose** (reference language, no error): English "No projects are shared with your account yet" and Czech "Kontaktní CTA" are accurate for the English/Czech audience; ru/uk use the more natural "no project added yet". Optional polish for the Czech owner: replace the jargon "CTA".

## 6. Legal content – owner input and legal review still required (nothing was invented)
No company data exists in the repository (identity comes from `.env`: `COMPANY_NAME`, `COMPANY_ICO`, `COMPANY_ADDRESS`, optional `COMPANY_DIC`/`COMPANY_REGISTER`, `CONTACT_*`, `PRICE_VAT_MODE`), so **no placeholder could be filled from verified data**. All 8 bracketed items per language are localized professionally and kept visible (a test checks that the count is equal in all four languages):

| # | Page | Open item |
|---|---|---|
| 1 | Terms | payment terms, deadlines and delivery |
| 2–5 | Terms #dreamers | who issues Dreamers Ad Credits · eligibility and how order value is counted · issuing/redemption · validity, transferability, cash value/refundability |
| 6 | Terms | ownership and licence of delivered work |
| 7 | Terms | complaints (*reklamace*) procedure |
| 8 | Terms | consumer withdrawal from a distance contract (after legal review) |
| – | Privacy | "last updated" date · Telegram operator, location, transfer safeguards · hosting provider name · e-mail provider name · EU/EEA transfers · retention of requests, accounts/project files and server logs |
| – | Pricing / Process | exact exclusions and extras (hosting, domain, copywriting, photos, maintenance) · timelines, review rounds, hosting and handover |
| – | `.env` | `PRICE_VAT_MODE`, `COMPANY_*`, `CONTACT_EMAIL/PHONE/LOCATION` (footer, careers mailto, privacy controller line show "[to be completed]" until set) |
| – | Content | confirm every listed package feature is actually delivered (Czech-language administration, analytics tools, "low running costs") |
The Russian and Ukrainian legal sections keep every qualification and exclusion of the English text but have **not** been reviewed by a lawyer or native legal translator.

## 7. Remaining untranslated / intentionally English
* **Template demos** (`templates/demos/*.html`): English sample sites (`<html lang="en">`); documented in `docs/templates/README.md`. Stillwave contains an English creator-attribution badge "Pixelite Catalog" (`href="#pixelite"`, non-functional) that is part of the template; left unchanged – decide whether to remove or link it.
* Staff area `/admin` (English by design), Telegram notifications (English by design), template prompt library (admin, English), logs.
* Brand and technical terms stay Latin: Pixelite, Dreamers Ad Credits, cookie, SEO, SaaS, GDPR, Telegram, DPH/IČO/DIČ (explained in brackets).
* Russian cookie-banner text is intentionally shorter than the English/Ukrainian text (fits the 320 px height budget, tested earlier); it covers the same facts except the sentence about a possible future optional service, which the dialog and policy page contain.
* The Ukrainian and Russian compare-table labels and long legal paragraphs were not read by a native speaker outside this review; a human proofread is still recommended before launch (as for Czech per `CLAUDE.md`).

## 8. Tests (actual results, this working tree)
| Suite | Result |
|---|---|
| `docker compose exec app php tests/run.php` (legacy-markup harness + `design.php` + `features.php` + `i18n.php`, each twice) | **1180 passed, 0 failed** (was 1094; +86 localization assertions) |
| Artifact gate `scripts/verify-endora-artifact.php --http` | **61 passed, 0 failed** |
| `tests/browser/new-design.mjs` (real Chrome: devices × en/cs/ru/uk × light/dark, menu, switches, notebook, gallery, forms) | **8781 passed, 0 failed** (advisories: the known 20 px native consent checkbox only) |
| `tests/browser/run.mjs` against a container with `DESIGN_DEFAULT=legacy` (the /legacy design) | **36924 passed, 0 failed** after updating three wording expectations (see below) |
| `tests/browser/platform.mjs` (staff, customer accounts, careers in 4 languages, projects, isolated preview origin) | **74 passed, 0 failed** |
New regression tests (`tests/i18n.php`): portfolio ru/uk content, `localized()` fallback rules, careers whole-language fallback + `lang="en"` + note, query preservation on language/design switch (gallery filters, `?template=`, `?package=`, `next` dropped, no stray `?`, canonical unchanged), no English interface text on 20 pages × ru/uk, validation errors in ru/uk, signed-in account empty states, 404/500 in ru/uk, terminology pins (заявка, аккаунт/обліковий запис, проєкт, no `(а)`, no mixed letters, Дизайны сайтов, Сравнение пакетов, Наши проекты, interactive demo wording, maintenance wording, two-column fix), independent Ukrainian copy, price/NBSP, equal count of legal placeholders, `inLanguage`, translated menu/TOC labels.
`tests/browser/run.mjs` hard-codes three expected strings that mirror the dictionaries («Консультация без обязательств», «Пока не знаю», «Ще не знаю»); they were updated to the new wording, no check was removed or weakened.
Not run: Google Search Console/CrUX style field checks; a native-speaker review; the new-design browser suite was not re-run after the final documentation-only edits.

## 9. Files changed
`lang/{en,cs,ru,uk}.php` · `config/portfolio.php` · `src/{Careers,Request,TemplateCatalog,bootstrap,helpers}.php` · `templates/{layout,partials/header,partials/toc,pages/{careers,order,portfolio,register}}.php` and the same in `templates/legacy/` (+ `legacy/layout.php`, `legacy/partials/header.php`) · `tests/{i18n.php (new),features.php,browser/run.mjs}` · `docs/i18n/{TERMINOLOGY,LOCALIZATION-REPORT}.md` (new) · `docs/templates/README.md` · `CLAUDE.md`. No schema, routing, pricing, consent or security changes.
Unrelated untracked folders `new/` and `meet-the-team/` were left untouched.

## 10. Outstanding issues
* Everything in section 6 (owner/legal input) and the proofreading in section 7.
* This localization work is **uncommitted** in the working tree, awaiting your review.
