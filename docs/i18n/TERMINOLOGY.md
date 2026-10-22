# Russian and Ukrainian terminology and style guide

English (`lang/en.php`) and Czech (`lang/cs.php`) are the reference languages for business content; `lang/ru.php` and `lang/uk.php` are written to read as if a
native copywriter had produced them, not as translations. The glossary sets **consistency, not literalism**: use a different construction wherever it is more
idiomatic. `tests/i18n.php` pins the critical terms below.

## Glossary

| Concept | Russian | Ukrainian | Notes |
|---|---|---|---|
| Website designs (catalog) | Дизайны сайтов | Дизайни сайтів | Nav label, page titles. Never just "Дизайны". |
| Project request (the order) | Заявка на проект | Заявка на проєкт | Form, account list ("Ваши заявки"), button "Отправить заявку". The start CTA stays "Начать проект" / "Почати проєкт". |
| Interactive website demo | Интерактивное демо | Інтерактивне демо | The embedded real template. Say it is a working site, not an image. |
| Animated card preview | Превью | Прев’ю | The short looping video on gallery cards. |
| Project preview (client's site being built) | Предпросмотр проекта | Попередній перегляд проєкту | Account page; private link. |
| Website maintenance | Обслуживание сайта | Обслуговування сайту | "Низкие затраты на обслуживание сайта", never "расходы на содержание". |
| Template website | Сайт на шаблоне | Сайт на шаблоні | Package name, in «» quotes in running text. |
| Basic website | Базовый сайт | Базовий сайт | |
| Custom website | Индивидуальный сайт | Індивідуальний сайт | |
| Website package | Пакет услуг по созданию сайта | Пакет послуг зі створення сайту | Full form in titles/intros; "пакет" elsewhere. |
| Starting price / "from" | Цена от · стартовая цена | Ціна від · стартова ціна | The card prefix is "От" / "Від". |
| Final price | Итоговая стоимость | Остаточна вартість | Always "стоимость/вартість" for the final amount. |
| Account | Аккаунт | Обліковий запис | One word per language, used everywhere (tests forbid mixing in "учётная запись", "акаунт"). |
| Contact form or button | Контактная форма или кнопка для связи | Контактна форма або кнопка зв’язку | Replaces the literal "contact call-to-action". |
| Package comparison | Сравнение пакетов | Порівняння пакетів | Not "пакеты рядом". |
| Our projects (portfolio) | Наши проекты | Наші проєкти | Not "Работы / Роботи". |
| Landing page | Лендинг | Лендинг | Project type and category copy. |
| Non-binding | без обязательств · ни к чему не обязывает | без зобов’язань · ні до чого не зобов’язує | |
| Dreamers Ad Credits | Dreamers Ad Credits | Dreamers Ad Credits | Product name, never translated; described as an advertising credit, not a cash discount. |
| Cookie, SEO, SaaS, GDPR, Telegram | unchanged | unchanged | Technical terms/brands stay in Latin script. |
| IČO / DIČ / DPH | Идентификационный номер организации (IČO) · Налоговый номер (DIČ) · НДС (DPH) | Ідентифікаційний номер організації (IČO) · Податковий номер (DIČ) · ПДВ (DPH) | Czech identifiers stay in brackets so they match official documents; no localized tax numbers are invented. |
| Controller (GDPR) | Контролёр персональных данных | Контролер персональних даних | |
| Complaint (reklamace) | Претензия (reklamace) | Претензія (reklamace) | The Czech term stays in brackets: it is the legal procedure. |
| Staff area | Раздел для сотрудников | Розділ для працівників | |

## Rules

* **No gendered forms.** Never `(а)`, `(ла)`. Use a neutral construction: «Мне известно, как используются мои данные» / «Мені відомо, як використовуватимуться мої дані»; «Пока не знаю» / «Ще не знаю».
* **Ukrainian is edited on its own**, not copied from the Russian. Spelling: `проєкт` (never `проект`), apostrophe `’` (U+2019), standard vocabulary (вакансія, обслуговування, строки, заява/заявка, прев’ю), no Russian calques ("мати місце", "в якості"). Russian uses `проект`, `ё` where it removes ambiguity (тёмная).
* **Buttons say what happens**: «Отправить заявку», «Открыть демо в новой вкладке», «Откликнуться по почте». Error messages say what to do next («Проверьте адрес электронной почты…», «Сократите текст»).
* **Numbers and currency**: prices keep the real amounts, a non-breaking space inside thousands (`2 999`, `10 000–30 000 CZK`) and `CZK`; the dollar amount is written «до 100 $» on cards and «100 USD» in the terms, as in the English source. A test fails if a space-separated number uses a normal space.
* **Do not add** promises, features, discounts or guarantees that the English source does not make. Legal text keeps every qualification, exclusion and bracketed owner placeholder.
* **Placeholders** (`[… – будет дополнено владельцем]`) stay square-bracketed and visible; they are counted by a test so none disappears silently.
* **Quotes**: «ёлочки» for names and settings; en dash with spaces ` – ` as the dash (as in the source). Sentence case for headings.
* **Length**: Russian/Ukrainian run 15–30 % longer than English. Check the 320 px banner, the desktop pill navigation (appears from 1360 px) and the compare table when you lengthen a string; the Russian cookie-banner text is intentionally shorter than the English/Ukrainian one so the banner stays within its height budget at 320 px.

## Where text lives (so nothing is missed)

| Source | Notes |
|---|---|
| `lang/{en,cs,ru,uk}.php` | all site copy; identical key sets enforced by `tests/run.php` |
| `config/portfolio.php` | per-language maps; read through `localized()` (language → English → any) |
| `Careers` rows (SQLite, staff-edited) | a language counts as translated only when **both** title and description exist; otherwise the whole English text is shown, marked `lang="en"` with the line "this vacancy is available in English only" |
| `config/team.php`, `config/templates.php` | names/roles/slugs only; copy lives in `lang/*` |
| `templates/demos/*.html` | **sample sites, deliberately English** (`<html lang="en">`) – see `docs/templates/README.md` |
| staff area (`/admin`, English only), Telegram messages (English), `tests/`, logs | internal, not translated by design |
| the hidden anti-spam field label | `a11y.honeypot` |
